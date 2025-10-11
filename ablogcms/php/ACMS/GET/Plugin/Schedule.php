<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated Plugin_Schedule モジュールは非推奨の機能です。代替手段として Schedule モジュールを使用してください。
 */
class ACMS_GET_Plugin_Schedule extends ACMS_GET_Schedule
{
    /**
     * @inheritdoc
     */
    public function get()
    {
        Deprecated::once('Plugin_Schedule モジュール', [
            'alternative' => ' Schedule モジュール',
        ]);
        return parent::get();
    }
}
