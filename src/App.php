<?php

namespace App;

use Psr\Container\ContainerInterface;
use App\Service\ServiceManager;

final class App
{
    private static ContainerInterface $serviceManager;

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     * @suppress PhanUndeclaredClassReference
     * @suppress PhanUndeclaredClassMethod
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        // In dev enviroment convert php errors to exceptions (including notice)
        // In prod enviroment see `docker logs`
        if (class_exists(\Dev\ErrorHandler::class)) {
            \Dev\ErrorHandler::register();
        }
        self::$serviceManager = new ServiceManager($config);
    }

    public function run(): void
    {
        self::get(Worker::class)->loop();
    }

    public static function has(string $id): bool
    {
        return self::$serviceManager->has($id);
    }

    public static function get(string $id)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return self::$serviceManager->get($id);
    }
}
