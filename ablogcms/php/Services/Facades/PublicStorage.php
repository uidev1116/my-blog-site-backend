<?php

namespace Acms\Services\Facades;

class PublicStorage extends Storage
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'public-storage';
    }
}
