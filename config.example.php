<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    'env' => 'prod', // prod | dev
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
    LoggerInterface::class => static function () {
        $stream = new StreamHandler(STDERR);
        //$stream->setFormatter(new \Dev\CliFormatter());
        return (new Logger('app'))->pushHandler($stream);
    },
];
