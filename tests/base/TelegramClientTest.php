<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnhandledExceptionInspection */

use M2T\App;
use M2T\Client\TelegramClient;
use Codeception\Test\Unit;

class TelegramClientTest extends Unit
{
    protected BaseTester $tester;

    public function testSendMessage(): void
    {
        new App();

        /** @var TelegramClient $client */
        $client = App::get(TelegramClient::class);

        $result = $client->getUpdates();
        static::assertIsArray($result);
    }
}
