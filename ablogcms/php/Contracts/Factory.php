<?php

namespace Acms\Contracts;

abstract class Factory
{
    /**
     * @var mixed
     */
    protected $instance;

    public function __construct()
    {
        $this->instance = $this->createInstance();
    }

    /**
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (is_callable([$this->instance, $method])) {
            /** @var callable $callback */
            $callback = [$this->instance, $method];
            return call_user_func_array($callback, $arguments);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on " . get_class($this->instance));
    }

    /**
     * @return mixed
     */
    abstract public function createInstance();
}
