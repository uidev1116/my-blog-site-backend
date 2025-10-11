<?php

namespace Acms\Services\Common;

class CorrectorFactory extends Factory
{
    /**
     * @param string $method
     * @param string $txt
     * @param mixed  $params
     * @param bool $magic_call
     * @return string|false
     */
    public function call($method, $txt, $params = [], $magic_call = false)
    {
        if (!is_array($params)) {
            $params = [$params];
        }
        $argument = [$txt, $params];
        if ($magic_call) {
            $argument = $params;
        }
        foreach ($this->_collection as $corrector) {
            $callback = [$corrector, $method];
            if (is_callable($callback)) {
                /** @var callable $callback */
                return call_user_func_array($callback, $argument);
            }
        }
        return false;
    }
}
