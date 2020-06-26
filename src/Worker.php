<?php

namespace M2T;

use AMQPExchange;
use M2T\Client\TelegramClient;
use Psr\Log\LoggerInterface;
use Throwable;

final class Worker
{
    private LoggerInterface $logger;
    private TelegramClient $telegram;
    private AMQPExchange $exchange;
    private int $memoryLimit;
    private int $interval;

    public function __construct(
        LoggerInterface $logger,
        TelegramClient $telegram,
        AMQPExchange $exchange
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->exchange = $exchange;
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
        $updates = $this->telegram->getUpdates();
        foreach ($updates as $update) {
            $payload = json_encode($update, JSON_THROW_ON_ERROR);
            $this->exchange->publish($payload, App::get('exchange'), AMQP_MANDATORY);
            $this->logger->debug('Update:', $update);
        }
        if (!$updates) {
            $this->logger->debug('No updates');
        }
    }
}
