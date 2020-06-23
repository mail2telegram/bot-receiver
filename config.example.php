<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    'env' => 'prod', // prod | dev
    'telegramToken' => 'XXX',
    'telegramLongPollingTimeout' => 5, // 0 for dev
    LoggerInterface::class => static function () {
        $stream = new StreamHandler(STDERR);
        //$stream->setFormatter(new \Dev\CliFormatter());
        return (new Logger('app'))->pushHandler($stream);
    },
];
