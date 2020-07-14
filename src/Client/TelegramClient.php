<?php

namespace M2T\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use M2T\App;
use Psr\Log\LoggerInterface;
use Throwable;

class TelegramClient
{
    protected const BASE_URL = 'https://api.telegram.org/bot';

    protected LoggerInterface $logger;
    protected ClientInterface $client;

    public function __construct(LoggerInterface $logger, ?ClientInterface $client = null)
    {
        $this->logger = $logger;
        $this->client = $client
            ?? new Client(
                [
                    'base_uri' => static::BASE_URL . App::get('telegramToken') . '/',
                    'timeout' => App::get('telegramLongPollingTimeout') + App::get('telegramTimeout'),
                ]
            );
    }

    public function getUpdates(int $offset, int $limit = 0): array
    {
        return $this->execute(
            'getUpdates',
            [
                'form_params' => [
                    'offset' => $offset,
                    'limit' => $limit ?: App::get('telegramUpdatesLimit'),
                    'timeout' => App::get('telegramLongPollingTimeout'),
                    'allowed_updates' => ['message', 'callback_query'],
                ],
            ]
        );
    }

    protected function execute(string $method, array $data): array
    {
        try {
            $response = $this->client->request('POST', $method, $data);
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
