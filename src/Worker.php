<?php

namespace App;

use App\Client\TelegramClient;
use Psr\Log\LoggerInterface;
use Throwable;

final class Worker
{
    private const MEMORY_LIMIT = 134_217_728; // 128MB
    private const USLEEP = 1_000_000;

    private LoggerInterface $logger;
    private TelegramClient $telegram;
    private ChatController $controller;

    public function __construct(
        LoggerInterface $logger,
        TelegramClient $telegram,
        ChatController $controller
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->controller = $controller;

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
                $this->logger->info('Worker task started');
                $this->task();
                $this->logger->info('Worker task finished');
            } catch (Throwable $e) {
                $this->logger->error((string) $e);
            }
        }
        $this->logger->info('Worker finished');
    }

    private function task(): void
    {
        $updates = $this->telegram->getUpdates();
        foreach ($updates as $update) {
            $this->controller->handle($update);
        }
    }
}
