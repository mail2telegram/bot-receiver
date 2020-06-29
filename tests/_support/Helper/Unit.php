<?php

namespace Helper;

use Codeception\Module;
use Codeception\Stub;
use M2T\App;
use Redis;

class Unit extends Module
{
    public function _initialize(): void
    {
        /** @noinspection PhpIncludeInspection */
        require_once codecept_root_dir() . '/vendor/autoload.php';

        $redis = Stub::make(
            Redis::class,
            [
                'set' => true,
                'get' => 0,
                'del' => 1,
            ]
        );

        new App([Redis::class => fn() => $redis]);
    }
}
