[![Test](https://github.com/mail2telegram/bot-receiver/workflows/Build/badge.svg)](https://github.com/mail2telegram/bot-receiver/actions?query=workflow%3ABuild)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=mail2telegram_bot-receiver&metric=alert_status)](https://sonarcloud.io/dashboard?id=mail2telegram_bot-receiver)
[![Docker Image Size (latest)](https://img.shields.io/docker/image-size/mail2telegram/bot-receiver/latest)](https://hub.docker.com/repository/docker/mail2telegram/bot-receiver)

Получает апдейты из Telegram и отправляет их в RabbitMQ.  
Смещение (id следующего апдейта) хранит в Redis.  
https://core.telegram.org/bots/api#getupdates
