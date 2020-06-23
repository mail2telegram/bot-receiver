<?php

namespace App\Client;

use App\App;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Throwable;

class TelegramClient
{
    protected const BASE_URL = 'https://api.telegram.org/bot';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getUpdates(): array
    {
        // @todo draft
        static $offset = 0;

        $data = [
            'form_params' => [
                'offset' => $offset,
                'limit' => 10,
                'timeout' => App::get('telegramLongPollingTimeout'),
            ],
        ];
        $updates = $this->execute('getUpdates', $data);

        if ($updates) {
            $offset = end($updates)['update_id'] + 1;
        }

        return $updates;
    }

    public function sendMessage(int $chatId, string $text, string $replyMarkup = ''): bool
    {
        $data = [
            'form_params' => [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ],
        ];
        if ($replyMarkup) {
            $data['form_params']['reply_markup'] = $replyMarkup;
        }
        $result = $this->execute('sendMessage', $data);
        return (bool) $result;
    }

    protected function execute(string $method, array $data): array
    {
        $client = new Client(
            [
                'base_uri' => static::BASE_URL . App::get('telegramToken') . '/',
                'timeout' => 10.0,
            ]
        );

        try {
            $response = $client->request('POST', $method, $data);
        } catch (Throwable $e) {
            $this->logger->error('Telegram: ' . $e);
            return [];
        }

        try {
            $response = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->logger->error('Telegram: json decode error');
            return [];
        }

        if (!isset($response['ok'])) {
            $this->logger->error('Telegram: wrong response');
            return [];
        }

        if ($response['ok'] !== true) {
            $this->logger->error('Telegram: ' . ($response['description'] ?? 'no description'));
            return [];
        }

        return $response['result'];
    }
}
