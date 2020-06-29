<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Unit;

use UnitTester;
use M2T\App;
use M2T\Client\TelegramClient;
use Codeception\Test\Unit;

class TelegramClientTest extends Unit
{
    protected UnitTester $tester;

    public function testSendMessage(): void
    {
        /** @var TelegramClient $client */
        $client = App::get(TelegramClient::class);

        $result = $client->getUpdates();
        static::assertIsArray($result);
    }
}
