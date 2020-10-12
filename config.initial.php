<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use pahanini\Monolog\Formatter\CliFormatter;
use Psr\Log\LoggerInterface;

return [
    'logLevel' => 'info', // @see \Psr\Log\LogLevel
    'workerMemoryLimit' => 134_217_728, // 128MB
    'workerInterval' => 1_000, // micro seconds
    'workerReconnectInterval' => 1_000_000, // micro seconds
    'telegramTimeout' => 5.0,
    'telegramLongPollingTimeout' => 2,
    'telegramUpdatesLimit' => 100, // 1-100
    'exchange' => 'telegram_update',
    'shared' => [
        LoggerInterface::class,
    ],
    LoggerInterface::class => static function ($c) {
        $stream = new StreamHandler(STDERR, $c->get('logLevel'));
        $stream->setFormatter(new CliFormatter());
        return (new Logger('app'))->pushHandler($stream);
    },
    Redis::class => static function ($c) {
        static $connect;
        if (null === $connect) {
            $connect = new Redis();
        }
        if (!$connect->isConnected()) {
            $config = $c->get('redis');
            if (!$connect->pconnect(
                $config['host'],
                $config['port'] ?? 6379,
                $config['timeout'] ?? 0.0,
                $config['persistentId'] ?? null,
                $config['retryInterval'] ?? 0,
                $config['readTimeout'] ?? 0.0
            )) {
                throw new RedisException('No Redis connection');
            }
        }
        return $connect;
    },
    AMQPConnection::class => static function ($c) {
        static $connect;
        if (null === $connect) {
            $config = $c->get('amqp');
            $connect = (new AMQPConnection(
                [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'login' => $config['user'],
                    'password' => $config['pwd'],
                    'heartbeat' => 60,
                ]
            ));
        }
        if (!$connect->isConnected()) {
            $connect->pconnect();
        }
        return $connect;
    },
];
