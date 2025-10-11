<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Logger;

class ACMS_POST_Module_Index_Status extends ACMS_POST
{
    public function post()
    {
        $this->validate($this->Post);

        $targetModules = [];

        if ($this->Post->isValidAll()) {
            $status = $this->Post->get('status');
            foreach ($this->Post->getArray('checks') as $check) {
                $ids = preg_split('@:@', $check, 2, PREG_SPLIT_NO_EMPTY);
                $moduleBlogId = $ids[0];
                $moduleId = $ids[1];
                if (!($moduleId = intval($moduleId))) {
                    continue;
                }
                if (!($moduleBlogId = intval($moduleBlogId))) {
                     continue;
                }

                if (!Module::canUpdate($moduleBlogId)) {
                    continue;
                }

                $this->save($moduleId, $status);

                $module = loadModule($moduleId);
                $targetModules[] = $module->get('label') . '（' . $module->get('identifier') . '）';
            }

            Logger::info('選択したモジュールIDのステータスを「' . $status . '」に設定しました', [
                'targetModules' => $targetModules,
            ]);
        } else {
            Logger::info('選択したモジュールIDのステータス設定に失敗しました');
        }

        return $this->Post;
    }

    /**
     * 保存
     *
     * @param int $id
     * @param 'open' | 'close' $status
     **/
    protected function save(int $id, string $status)
    {
        $sql = SQL::newUpdate('module');
        $sql->addUpdate('module_status', $status);
        $sql->addUpdate('module_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $sql->addWhereOpr('module_id', $id);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * バリデート
     * @param \Field_Validation $Field
     **/
    protected function validate(\Field_Validation $Field)
    {
        $Field->setMethod('module', 'operative', $this->isOperable(BID));
        $Field->setMethod('checks', 'required');
        $Field->setMethod('status', 'required');
        $Field->setMethod('status', 'in', ['open', 'close']);
        $Field->validate(new ACMS_Validator());
    }

    /**
     * モジュールが所属するブログにおいてモジュールの更新が可能なユーザーかどうか
     *
     * @param int $moduleBlogId
     * @return bool
     */
    protected function isOperable(int $moduleBlogId = BID): bool
    {
        if (Module::canBulkStatusChange($moduleBlogId)) {
            return true;
        }

        return false;
    }
}
