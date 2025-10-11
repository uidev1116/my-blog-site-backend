<?php

namespace Acms\Services\Common;

class HookFactory extends Factory
{
    /**
     * @param string $timingMethod
     * @param mixed  $params
     * @return array
     */
    public function call($timingMethod, $params = [])
    {
        $rv = [];
        if (!is_array($params)) {
            $params = [$params];
        }
        foreach ($this->_collection as $Hook) {
            if (is_callable([$Hook, $timingMethod])) {
                $rv[] = get_class($Hook);
                /** @var callable $callback */
                $callback = [$Hook, $timingMethod];
                call_user_func_array($callback, $params);
            }
        }
        return $rv;
    }

    /**
     * @param string $ns
     * @return bool|object
     */
    public function getHook($ns)
    {
        if (isset($this->_collection[$ns])) {
            return $this->_collection[$ns];
        } else {
            return false;
        }
    }
}
