<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Database as DB;

class ACMS_POST_Module_Delete extends ACMS_POST_Module
{
    public function post()
    {
        $Module = $this->extract('module');
        $Module->reset();
        $this->Post->setMethod('module', 'midIsNull', ($mid = idval($this->Get->get('mid'))));

        $this->Post->setMethod('module', 'operative', Module::canDelete(BID));
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $this->delete($mid);

            Logger::info('「' . $Module->get('label') . '（' . $Module->get('identifier') . '）」モジュールを削除しました', [
                'mid' => $mid,
            ]);
        } else {
            Logger::info('モジュールの削除に失敗しました', [
                'mid' => $mid,
            ]);
        }

        return $this->Post;
    }

    /**
     * モジュールの削除
     * 現在のブログで管理しているモジュールのみ削除可能
     *
     * @param int $mid
     *
     * @return void
     */
    public function delete($mid)
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newDelete('module');
        $SQL->addWhereOpr('module_id', $mid);
        $DB->query($SQL->get(dsn()), 'exec');

        //--------
        // delete
        $SQL    = SQL::newDelete('config');
        $SQL->addWhereOpr('config_module_id', $mid);
        $DB->query($SQL->get(dsn()), 'exec');

        Config::forgetCache(null, null, $mid);

        Common::deleteField('mid', $mid, null);

        $this->Post->set('edit', 'delete');
    }
}
