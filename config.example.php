<?php

use M2T\App;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use pahanini\Monolog\Formatter\CliFormatter;
use Psr\Log\LoggerInterface;

return [
    'workerMemoryLimit' => 134_217_728, // 128MB
    'workerInterval' => 1_000, // micro seconds
    'telegramToken' => 'XXX',
    'telegramLongPollingTimeout' => 2,
    'telegramUpdatesLimit' => 100, // 1-100
    'exchange' => 'telegram_update',
    'redis' => [
        'host' => 'm2t_redis',
    ],
    'amqp' => [
        'host' => 'm2t_rabbitmq',
        'port' => '5672',
        'user' => 'guest',
        'pwd' => 'guest',
    ],
    'shared' => [
        LoggerInterface::class,
    ],
    LoggerInterface::class => static function () {
        $stream = new StreamHandler(STDERR);
        $stream->setFormatter(new CliFormatter());
        return (new Logger('app'))->pushHandler($stream);
    },
    Redis::class => static function () {
        static $connect;
        if (null === $connect) {
            $connect = new Redis();
        }
        if (!$connect->isConnected() && !$connect->pconnect(App::get('redis')['host'])) {
            throw new RuntimeException('No Redis connection');
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
    AMQPExchange::class => static function () {
        return new AMQPExchange(new AMQPChannel(App::get(AMQPConnection::class)));
    },
];
