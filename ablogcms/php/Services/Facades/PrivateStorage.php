<?php

namespace Acms\Services\Facades;

class PrivateStorage extends Storage
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'private-storage';
    }
}
