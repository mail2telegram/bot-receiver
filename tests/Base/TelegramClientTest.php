<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Base;

use BaseTester;
use Codeception\Test\Unit;
use M2T\Client\TelegramClient;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;

class TelegramClientTest extends Unit
{
    protected BaseTester $tester;

    public function testSendMessage(): void
    {
        $logHandler = new TestHandler();
        $logger = (new Logger('test'))->pushHandler($logHandler);
        $client = new TelegramClient($logger);

        $result = $client->getUpdates(0);
        static::assertIsArray($result);
        static::assertFalse($logHandler->hasRecords(LogLevel::ERROR));
    }
}
