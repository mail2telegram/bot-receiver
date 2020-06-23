<?php

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

pcntl_async_signals(true);

(new App\App())->run();
