<?php

namespace App;

use App\Client\TelegramClient;
use Psr\Log\LoggerInterface;

class ChatController
{
    protected LoggerInterface $logger;
    private TelegramClient $telegram;

    public function __construct(LoggerInterface $logger, TelegramClient $telegram)
    {
        $this->logger = $logger;
        $this->telegram = $telegram;
    }

    public function handle(array $update): void
    {
        $this->logger->debug('Update:', $update);

        // @todo handle updates here

        if (isset($update['message']['text']) && $update['message']['text'] === '/register') {
            $this->draftRegister($update);
            return;
        }

        if (isset($update['callback_query']['data']) && ['callback_query']['data'] === 'Cancel') {
            // do something
            return;
        }
    }

    public function draftRegister(array $update): void
    {
        $chatId = $update['message']['chat']['id'];
        /** @noinspection JsonEncodingApiUsageInspection */
        $replyMarkup = json_encode(
            [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Cancel',
                            'callback_data' => 'Cancel',
                        ],
                    ],
                ],
            ],
        );
        $this->telegram->sendMessage($chatId, 'Please enter your email address, login and password', $replyMarkup);
    }
}
