<?php

namespace Acms\Services\Facades;

class LocalStorage extends Storage
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'storage';
    }
}
