<?php

namespace Dev;

use Whoops\Handler\PlainTextHandler;
use Whoops\Run;

class ErrorHandler
{
    public static function register(): void
    {
        (new Run())
            ->pushHandler(
                new PlainTextHandler()
            )
            ->register();
    }
}
