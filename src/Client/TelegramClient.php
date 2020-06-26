<?php

namespace App\Client;

use M2T\App;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Redis;
use Throwable;

class TelegramClient
{
    protected const BASE_URL = 'https://api.telegram.org/bot';

    protected LoggerInterface $logger;
    protected Redis $redis;
    protected Client $client;

    public function __construct(LoggerInterface $logger, Redis $redis)
    {
        $this->logger = $logger;
        $this->redis = $redis;
        $this->client = new Client(
            [
                'base_uri' => static::BASE_URL . App::get('telegramToken') . '/',
                'timeout' => App::get('telegramLongPollingTimeout') + 2.0,
            ]
        );
    }

    public function getUpdates(): array
    {
        $offset = (int) $this->redis->get('telegramUpdatesOffset') ?: 0;
        $data = [
            'form_params' => [
                'offset' => $offset,
                'limit' => App::get('telegramUpdatesLimit'),
                'timeout' => App::get('telegramLongPollingTimeout'),
            ],
        ];
        $updates = $this->execute('getUpdates', $data);
        if ($updates) {
            $offset = end($updates)['update_id'] + 1;
            $this->redis->set('telegramUpdatesOffset', $offset);
        }
        return $updates;
    }

    protected function execute(string $method, array $data): array
    {
        try {
            $response = $this->client->request('POST', $method, $data);
        } catch (Throwable $e) {
            $this->logger->error('Telegram: ' . $e);
            return [];
        }

        // @todo Теоретически можно избежать серализации/десериализации,
        // т.к. дальше в очередь уходит один апдейт в исходном виде.
        // Мы получаем несколько апдейтов, нужно либо получать по одному, либо резать на части.
        // Также в любом случае нужен id последнего апдейта.
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
