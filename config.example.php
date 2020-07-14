<?php

return [
    'logLevel' => 'debug',
    'telegramToken' => getenv('TEST_TELEGRAM_TOKEN') ?: 'XXX',
    'redis' => [
        'host' => 'm2t_redis',
    ],
    'amqp' => [
        'host' => 'm2t_rabbitmq',
        'port' => '5672',
        'user' => 'guest',
        'pwd' => 'guest',
    ],
];
