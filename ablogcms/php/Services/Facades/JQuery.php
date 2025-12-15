<?php

namespace Acms\Services\Facades;

use Acms\Services\Facades\Facade;

/**
 * Class JQuery
 *
 * @method static string getVersion() jQueryのバージョンを取得する
 * @method static string getMigrate() jQuery Migrateの設定値を取得する
 */
class JQuery extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'jquery';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
