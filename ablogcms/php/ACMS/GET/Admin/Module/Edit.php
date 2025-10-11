<?php

class ACMS_GET_Admin_Module_Edit extends ACMS_GET_Admin_Edit
{
    /**
     * モジュールID
     *
     * @var int|null
     */
    protected $moduleId = null;

    /**
     * ルールID
     *
     * @var int|null
     */
    protected $ruleId = null;

    /**
     * @inheritDoc
     */
    protected function init()
    {
        if (!$this->ruleId = idval($this->Get->get('rid'))) {
            $this->ruleId = null;
        }
        if (!$this->moduleId = idval($this->Get->get('mid'))) {
            $this->moduleId = null;
        }
    }

    public function auth()
    {
        if (is_null($this->moduleId)) {
            // モジュールを作成する場合
            if (Module::canCreate(BID)) {
                return true;
            }
            return false;
        }
        // モジュールを編集する場合
        if (Module::canUpdate(BID)) {
            return true;
        }
        if (Module::canUpdateWithShortcut($this->moduleId, $this->ruleId)) {
            return true;
        }
        return false;
    }

    /**
     *  ショートカットによる認可チェック
     *
     * @return bool
     */
    protected function shortcutAuthorization(): bool
    {
        return Auth::checkShortcut([
            'mid' => $this->moduleId,
            'rid' => $this->ruleId
        ]);
    }

    public function edit(&$Tpl)
    {
        $Module = $this->Post->getChild('module');

        if ($Module->isNull() && (empty($this->edit) || ($this->edit !== 'insert' && $this->edit !== 'delete'))) {
            $_Module = loadModule($this->moduleId);
            $_Module->setField('field_', $_Module->get('field'));
            $start = $_Module->get('start');
            $end = $_Module->get('end');
            if (!empty($start)) {
                $date_time = explode(' ', $start);
                $date = $date_time[0];
                $time = $date_time[1];
                $_Module->setField('start_date', $date);
                $_Module->setField('start_time', $time);
            }
            if (!empty($end)) {
                $date_time = explode(' ', $end);
                $date = $date_time[0];
                $time = $date_time[1];
                $_Module->setField('end_date', $date);
                $_Module->setField('end_time', $time);
            }
            $Module->overload($_Module);
        }

        $this->buildArgLabels($Module);

        if (in_array($Module->get('name'), ['Blog_Field', 'Entry_Field', 'Category_Field', 'User_Field'], true)) {
            $Module->delete('id');
        }

        $Field = $this->Post->getChild('field');
        if ($this->Post->isNull() && !empty($this->moduleId)) {
            $Field->overload(loadModuleField($this->moduleId));
        }

        return true;
    }

    /**
     * モジュールが所属するブログIDを取得
     *
     * @param int $moduleId
     * @return int
     */
    protected function getModuleBlogId(int $moduleId)
    {
        $sql = SQL::newSelect('module');
        $sql->addSelect('module_blog_id');
        $sql->addWhereOpr('module_id', $moduleId);
        return DB::query($sql->get(dsn()), 'one');
    }
}
