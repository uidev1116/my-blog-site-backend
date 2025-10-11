<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Logger;

class ACMS_POST_Module_Index_Blog extends ACMS_POST_Module
{
    public function post()
    {
        if (!($bid = intval($this->Post->get('bid')))) {
            $bid = null;
        }
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('module', 'operable', is_int($bid) && Module::canBulkBlogChange($bid));

        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $DB = DB::singleton(dsn());
            $targetModules = [];
            $errorModules = [];

            foreach (array_reverse($this->Post->getArray('checks')) as $mid) {
                $id = preg_split('@:@', $mid, 2, PREG_SPLIT_NO_EMPTY);
                $mid = $id[1];
                if (!($mid = intval($mid))) {
                    continue;
                }

                $Module = loadModule($mid);
                $identifier = $Module->get('identifier');
                $scope = $Module->get('scope');
                $moduleBlogId = (int)$Module->get('blog_id');

                if (!Module::canUpdate($moduleBlogId)) {
                    // モジュールの更新が許可されていない
                    $errorModules[] = $Module->get('label') . '（' . $Module->get('identifier') . '）';
                    continue;
                }

                if (!Module::double($identifier, $mid, $scope, $bid)) {
                    // 移動先のブログに重複したモジュールが存在する
                    $errorModules[] = $Module->get('label') . '（' . $Module->get('identifier') . '）';
                    continue;
                }

                // module
                $SQL = SQL::newUpdate('module');
                $SQL->addUpdate('module_blog_id', $bid);
                $SQL->addUpdate('module_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
                $SQL->addWhereOpr('module_id', $mid);
                $DB->query($SQL->get(dsn()), 'exec');

                // config
                $SQL    = SQL::newUpdate('config');
                $SQL->addUpdate('config_blog_id', $bid);
                $SQL->addWhereOpr('config_module_id', $mid);
                $DB->query($SQL->get(dsn()), 'exec');
                Config::forgetCache(null, null, $mid);

                // field
                $SQL    = SQL::newUpdate('field');
                $SQL->addUpdate('field_blog_id', $bid);
                $SQL->addWhereOpr('field_mid', $mid);
                $DB->query($SQL->get(dsn()), 'exec');
                Common::deleteFieldCache('mid', $mid);

                $targetModules[] = $Module->get('label') . '（' . $Module->get('identifier') . '）';
            }
            if (!empty($targetModules)) {
                Logger::info('選択したモジュールIDを「' . ACMS_RAM::blogName($bid) . '」ブログに移動しました', [
                    'targetModules' => $targetModules,
                ]);
            }
            if (!empty($errorModules)) {
                $this->addError(gettext('移動に失敗したモジュールIDがあります'));
                Logger::info('選択したモジュールIDのブログ移動に失敗しました', [
                    'errorModules' => $errorModules,
                ]);
            }
        } else {
            Logger::info('選択したモジュールIDのブログ移動に失敗しました');
        }

        return $this->Post;
    }
}
