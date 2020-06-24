<?php

namespace App\Service;

use App\App;
use Redis;
use RuntimeException;

final class RedisService extends Redis
{
    private static ?self $connect = null;

    public static function it(): self
    {
        if (null === self::$connect) {
            self::$connect = new self();
        }
        if (!self::$connect->isConnected() && !self::$connect->pconnect(App::get('redis')['host'])) {
            throw new RuntimeException('No Redis connection');
        }
        return self::$connect;
    }
}
