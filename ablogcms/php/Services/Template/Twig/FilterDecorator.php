<?php

namespace Acms\Services\Template\Twig;

class FilterDecorator
{
    private $corrector;

    public function __construct($corrector)
    {
        $this->corrector = $corrector;
    }

    /**
     * 校正オプション呼び出し時に引数を配列に変換して呼び出す
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, $arguments)
    {
        $newArguments = [];
        $newArguments[] = $arguments[0];
        $newArguments[] = array_slice($arguments, 1);

        $callback = [$this->corrector, $name];
        if (!is_callable($callback)) {
            throw new \BadMethodCallException("Method {$name} is not callable on corrector.");
        }
        /** @var callable $callback */
        return call_user_func_array($callback, $newArguments);
    }
}
