<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnhandledExceptionInspection */

use M2T\App;
use App\Client\TelegramClient;
use App\Storage;
use Codeception\Test\Unit;

class TelegramClientTest extends Unit
{
    protected BaseTester $tester;

    public function testSendMessage(): void
    {
        new App();

        /** @var \App\Client\TelegramClient $client */
        $client = App::get(TelegramClient::class);

        $result = $client->getUpdates();
        static::assertIsArray($result);
    }
}
