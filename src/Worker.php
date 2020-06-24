<?php

namespace App;

use AMQPExchange;
use App\Client\TelegramClient;
use Psr\Log\LoggerInterface;
use Throwable;

final class Worker
{
    private const MEMORY_LIMIT = 134_217_728; // 128MB
    private const USLEEP = 1_000;

    private LoggerInterface $logger;
    private TelegramClient $telegram;
    private AMQPExchange $exchange;

    public function __construct(
        LoggerInterface $logger,
        TelegramClient $telegram,
        AMQPExchange $exchange
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->exchange = $exchange;

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
            if (memory_get_usage(true) >= self::MEMORY_LIMIT) {
                $this->logger->warning('Worker out of memory');
                break;
            }
            usleep(self::USLEEP);
            try {
                $this->task();
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
     * @throws \JsonException
     */
    private function task(): void
    {
        $debugEnabled = App::get('env') !== 'prod';
        $updates = $this->telegram->getUpdates();
        foreach ($updates as $update) {
            $payload = json_encode($update, JSON_THROW_ON_ERROR);
            $this->exchange->publish($payload, App::get('exchange'), AMQP_MANDATORY);
            if ($debugEnabled) {
                $this->logger->debug('Update:', $update);
            }
        }
        if ($debugEnabled && !$updates) {
            $this->logger->debug('No updates');
        }
    }
}
