<?php

use M2T\App;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use pahanini\Monolog\Formatter\CliFormatter;
use Psr\Log\LoggerInterface;

return [
    'logLevel' => 'info', /** @see \Psr\Log\LogLevel */
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
    LoggerInterface::class => static function () {
        $stream = new StreamHandler(STDERR, App::get('logLevel'));
        $stream->setFormatter(new CliFormatter());
        return (new Logger('app'))->pushHandler($stream);
    },
    Redis::class => static function () {
        static $connect;
        if (null === $connect) {
            $connect = new Redis();
        }
        if (!$connect->isConnected()) {
            $config = App::get('redis');
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
    AMQPConnection::class => static function () {
        static $connect;
        if (null === $connect) {
            $config = App::get('amqp');
            $connect = (new AMQPConnection(
                [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'login' => $config['user'],
                    'password' => $config['pwd'],
                ]
            ));
        }
        if (!$connect->isConnected()) {
            $connect->pconnect();
        }
        return $connect;
    },
];
