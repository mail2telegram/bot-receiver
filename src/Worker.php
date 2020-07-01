<?php

namespace M2T;

use AMQPExchange;
use M2T\Client\TelegramClient;
use Psr\Log\LoggerInterface;
use Redis;
use RedisException;
use Throwable;

final class Worker
{
    private LoggerInterface $logger;
    private TelegramClient $telegram;
    private AMQPExchange $exchange;
    private Redis $redis;
    private int $memoryLimit;
    private int $interval;

    public function __construct(
        LoggerInterface $logger,
        TelegramClient $telegram,
        AMQPExchange $exchange,
        Redis $redis
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->exchange = $exchange;
        $this->redis = $redis;
        $this->interval = App::get('workerInterval');
        $this->memoryLimit = App::get('workerMemoryLimit');

        $this->logger->info('Worker started');
        pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        pcntl_signal(SIGINT, [$this, 'signalHandler']);
    }

    public function signalHandler($signo): void
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
                if (!defined('TERMINATED')) {
                    define('TERMINATED', true);
                    $this->logger->info('Worker terminated signal');
                }
        }
    }

    public function loop(): void
    {
        while (true) {
            if (defined('TERMINATED')) {
                break;
            }
            if (memory_get_usage(true) >= $this->memoryLimit) {
                $this->logger->warning('Worker out of memory');
                break;
            }
            usleep($this->interval);
            try {
                $this->task();
            } catch (RedisException $e) {
                $this->logger->error((string) $e);
                sleep(1);
                $this->redis = App::get(Redis::class);
            } catch (Throwable $e) {
                $this->logger->error((string) $e);
            }
        }
        $this->logger->info('Worker finished');
    }

    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \RedisException
     * @throws \JsonException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    private function task(): void
    {
        $offset = (int) $this->redis->get('telegramUpdatesOffset') ?: 0;
        $updates = $this->telegram->getUpdates($offset);
        if ($updates) {
            $offset = end($updates)['update_id'] + 1;
            $this->redis->set('telegramUpdatesOffset', $offset);
        } else {
            $this->logger->debug('No updates');
        }
        foreach ($updates as $update) {
            // @todo Теоретически можно избежать серализации/десериализации,
            // т.к. от Telegram мы получаем json в том же виде, как отправляем его дальше в очередь.
            // Мы получаем несколько апдейтов, нужно либо получать по одному, либо резать на части.
            // Также в любом случае из json нужно вытащить id последнего апдейта.
            $payload = json_encode($update, JSON_THROW_ON_ERROR);
            // @todo Обработка апдейтов другого типа (не message)
            $routingKey = (string) ($update['message']['from']['id'] ?? 1);
            $this->exchange->publish($payload, $routingKey, AMQP_MANDATORY);
            $this->logger->debug('Update:', $update);
        }
    }
}
