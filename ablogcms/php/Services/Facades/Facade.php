<?php

namespace Acms\Services\Facades;

use Acms\Services\Container;

abstract class Facade
{
    /**
     * @var \Acms\Services\Container
     */
    protected static $container;

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getServiceAlias()
    {
        throw new \RuntimeException('Service does not defined getServiceAlias method.');
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return false;
    }

    /**
     * @param string $alias
     *
     * @return mixed
     */
    protected static function getServiceInstance($alias)
    {
        return static::$container->make($alias);
    }

    /**
     * get service instance
     *
     * @return mixed
     */
    public static function getInstance()
    {
        return static::getServiceInstance(static::getServiceAlias());
    }

    /**
     * @param \Acms\Services\Container $container
     *
     * @return void
     */
    public static function setContainer(Container $container)
    {
        static::$container = $container;
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     */
    public static function __callStatic($method, array $arguments)
    {
        $class = get_called_class();
        if (static::isCache() && property_exists($class, 'instance') && $class::$instance) {
            $instance = $class::$instance;
        } else {
            $instance = static::getServiceInstance(static::getServiceAlias());
        }
        if (static::isCache()) {
            $class::$instance = $instance;
        }
        if (is_callable([$instance, $method])) {
            /** @var callable $callback */
            $callback = [$instance, $method];
            return call_user_func_array($callback, $arguments);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on " . get_class($instance));
    }
}
