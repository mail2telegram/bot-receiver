<?php

namespace M2T;

use AMQPChannel;
use AMQPConnection;
use AMQPException;
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
    private Redis $redis;
    private AMQPConnection $amqp;
    private ?AMQPExchange $exchange;
    private int $memoryLimit;
    private int $interval;

    public function __construct(
        LoggerInterface $logger,
        TelegramClient $telegram,
        Redis $redis,
        AMQPConnection $amqp
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->redis = $redis;
        $this->amqp = $amqp;
        $this->interval = App::get('workerInterval');
        $this->memoryLimit = App::get('workerMemoryLimit');

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->setExchange();

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
            } catch (Throwable $e) {
                $this->logger->error((string) $e);
                if (is_a($e, RedisException::class)) {
                    $this->reconnectRedis();
                } elseif (is_a($e, AMQPException::class)) {
                    $this->reconnectAMQP();
                }
            }
        }
        $this->logger->info('Worker finished');
    }

    /** @SuppressWarnings(PHPMD.EmptyCatchBlock) */
    private function reconnectRedis(): void
    {
        $config = App::get('redis');
        usleep(App::get('workerReconnectInterval'));
        try {
            /** @phan-suppress-next-line PhanParamTooManyInternal */
            $this->redis->pconnect(
                $config['host'],
                $config['port'] ?? 6379,
                $config['timeout'] ?? 0.0,
                $config['persistentId'] ?? null,
                $config['retryInterval'] ?? 0,
                $config['readTimeout'] ?? 0.0
            );
        } catch (Throwable $e) {
        }
    }

    /** @SuppressWarnings(PHPMD.EmptyCatchBlock) */
    private function reconnectAMQP(): void
    {
        usleep(App::get('workerReconnectInterval'));
        try {
            if ($this->amqp->reconnect()) {
                $this->setExchange();
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * @throws \AMQPException
     */
    private function setExchange(): void
    {
        $x = new AMQPExchange(new AMQPChannel($this->amqp));
        $x->setName(App::get('exchange'));
        $x->setFlags(AMQP_DURABLE);
        $x->setType('x-consistent-hash');
        $x->declareExchange();
        $this->exchange = $x;
    }

    /**
     * @throws \AMQPException
     * @throws \RedisException
     * @throws \JsonException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    private function task(): void
    {
        static $offset = 0;
        if ($offset === 0) {
            $offset = (int) $this->redis->get('telegramUpdatesOffset');
        }

        $updates = $this->telegram->getUpdates($offset);
        if (!$updates) {
            //$this->logger->debug('No updates');
            return;
        }
        // Сохраним сразу, лучше ничего не отправим вообще, чем отправим повторно, если вдруг редис отвалится
        // Но при этом $offset меняем после фактической отправки в очередь.
        $this->redis->set('telegramUpdatesOffset', end($updates)['update_id'] + 1);

        foreach ($updates as $update) {
            // @todo Теоретически можно избежать десериализации,
            // т.к. от Telegram мы получаем json в том же виде, как отправляем его дальше в очередь.
            // Мы получаем несколько апдейтов, нужно либо получать по одному, либо резать на части.
            $payload = json_encode($update, JSON_THROW_ON_ERROR);

            // @todo Обработка апдейтов другого типа (не message)
            $routingKey = (string) ($update['message']['from']['id'] ?? 1);

            $this->exchange->publish($payload, $routingKey, AMQP_MANDATORY);
            $offset = $update['update_id'] + 1;

            $this->logger->debug('Update:', $update);
        }
    }
}
