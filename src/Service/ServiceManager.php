<?php

namespace App\Service;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use App\App;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Redis;
use ReflectionClass;

final class ServiceManager implements ContainerInterface
{
    private array $config;
    private array $container = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge(
            [
                'shared' => [
                    LoggerInterface::class,
                ],
                Redis::class => [RedisService::class, 'it'],
                LoggerInterface::class => static function () {
                    /** @phan-suppress-next-line PhanTypeMismatchArgument */
                    return (new Logger('app'))->pushHandler(new StreamHandler(STDERR, Logger::INFO));
                },
                AMQPConnection::class => static function () {
                    static $connect;
                    if (null === $connect) {
                        $config = App::get('amqp');
                        $connect = (new AMQPConnection(
                            [
                                'host' => $config['host'],
                                'port' => $config['port'],
                                'login' => $config['user'],
                                'password' => $config['pwd'],
                            ]
                        ));
                    }
                    if (!$connect->isConnected()) {
                        $connect->pconnect();
                    }
                    return $connect;
                },
                AMQPExchange::class => static function () {
                    return new AMQPExchange(new AMQPChannel(App::get(AMQPConnection::class)));
                },
            ],
            require __DIR__ . '/../../config.php',
            $config
        );
        /** @phan-suppress-next-line PhanTypeNoPropertiesForeach */
        foreach ($this->config['shared'] as $value) {
            $this->container[$value] = null;
        }
    }

    public function has($id): bool
    {
        return array_key_exists($id, $this->config) || class_exists($id);
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \App\Service\ServiceNotFoundException
     */
    public function get($id)
    {
        if (array_key_exists($id, $this->config)) {
            if (!is_callable($this->config[$id])) {
                return $this->config[$id];
            }
            if (isset($this->container[$id])) {
                return $this->container[$id];
            }
            if (array_key_exists($id, $this->container)) {
                $this->container[$id] = call_user_func($this->config[$id]);
                return $this->container[$id];
            }
            return call_user_func($this->config[$id]);
        }

        if (class_exists($id)) {
            return $this->autowire($id);
        }

        throw new ServiceNotFoundException("$id not found");
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \App\Service\ServiceNotFoundException
     */
    private function autowire($id)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $class = new ReflectionClass($id);
        $constructor = $class->getConstructor();
        $args = [];
        if ($constructor) {
            $params = $constructor->getParameters();
            foreach ($params as $param) {
                if ($param->isOptional()) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $args[] = $param->getDefaultValue();
                } else {
                    $paramClass = $param->getClass();
                    if (!$paramClass) {
                        throw new ServiceNotFoundException("Can't resolve param '{$param->getName()}' for $id");
                    }
                    $args[] = $this->get($paramClass->getName());
                }
            }
        }
        return $class->newInstanceArgs($args);
    }
}
