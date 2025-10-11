<?php

namespace Acms\Services\Common;

use Acms\Contracts\Singleton;

class Factory extends Singleton
{
    /**
     * @var array
     */
    protected $_collection = [];

    /**
     * @param string $ns
     * @param $corrector
     * @return void
     */
    public function attach($ns, $corrector)
    {
        $this->_collection[$ns] = $corrector;
    }

    /**
     * @param string $ns
     * @return void
     */
    public function detach($ns)
    {
        if (isset($this->_collection[$ns])) {
            unset($this->_collection[$ns]);
        }
    }

    /**
     * @return array
     */
    public function getCollection(): array
    {
        return $this->_collection;
    }
}
