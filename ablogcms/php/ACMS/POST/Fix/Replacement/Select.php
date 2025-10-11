<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database as DB;

class ACMS_POST_Fix_Replacement_Select extends ACMS_POST_Fix
{
    public function post()
    {
        $field = $this->extract('fix');
        if (!$this->validate($field)) {
            $this->Post->set('step', 'confirm');
            return $this->Post;
        }
        [$target, $pattern, $replacement, $filter] = $this->getReplaceSetting($field);
        $targetBlogIds = $this->targetBlog($field->get('fix_replacement_target_blog') === 'descendant');

        $updated = 0;
        foreach ($this->Post->getArray('checks') as $identifer) {
            if (is_string($identifer) && $identifer !== '') {
                $updated += $this->replace($target, $identifer, $pattern, $replacement, $targetBlogIds, $filter);
            }
        }

        $this->completeProcess($updated);
        $this->saveLog($target, $updated, $pattern, $replacement);

        return $this->Post;
    }

    /**
     * バリデート
     *
     * @param Field_Validation $field
     * @return bool
     */
    protected function validate(Field_Validation $field): bool
    {
        if (!sessionWithAdministration()) {
            return false;
        }
        $this->Post->setMethod('checks', 'required');
        $this->Post->validate(new ACMS_Validator());

        return $this->Post->isValidAll();
    }

    /**
     * テキスト置換を実行
     *
     * @param string $target
     * @param non-empty-string $identifer
     * @param string $pattern
     * @param string $replacement
     * @param int[] $targetBlogIds
     * @param string $filter
     * @return int
     */
    public function replace(string $target, string $identifer, string $pattern, string $replacement, array $targetBlogIds, string $filter = ''): int
    {
        $status = false;

        switch ($target) {
            case 'title':
                $status = $this->replaceEntryTitle((int) $identifer, $pattern, $replacement, $targetBlogIds);
                break;
            case 'unit':
                $status = $this->replaceUnitText($identifer, $pattern, $replacement, $targetBlogIds);
                break;
            case 'custom_unit':
                $status = $this->replaceFieldText('unit_id', $identifer, $pattern, $replacement, $targetBlogIds, $filter);
                break;
            case 'field':
                $status = $this->replaceFieldText('eid', $identifer, $pattern, $replacement, $targetBlogIds, $filter);
                break;
        }
        if ($status) {
            return 1;
        }
        return 0;
    }

    /**
     * エントリータイトルをテキスト置換
     *
     * @param int $eid
     * @param string $pattern
     * @param string $replacement
     * @param int[] $targetBlogIds
     * @return bool
     */
    protected function replaceEntryTitle(int $eid, string $pattern, string $replacement, array $targetBlogIds): bool
    {
        if (empty($eid)) {
            return false;
        }
        $title = ACMS_RAM::entryTitle($eid);
        $title = preg_replace("@({$pattern})@iu", $replacement, $title);
        $sql = SQL::newUpdate('entry');
        $sql->addUpdate('entry_title', $title);
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereIn('entry_blog_id', $targetBlogIds);
        DB::query($sql->get(dsn()), 'exec');

        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));

        return true;
    }

    /**
     * テキストユニットのテキスト置換
     *
     * @param non-empty-string $utid
     * @param string $pattern
     * @param string $replacement
     * @param int[] $targetBlogIds
     * @return bool
     */
    protected function replaceUnitText(string $utid, string $pattern, string $replacement, array $targetBlogIds): bool
    {
        if (empty($utid)) {
            return false;
        }
        $unit = ACMS_RAM::unitField1($utid);
        $unit = preg_replace("@({$pattern})@iu", $replacement, $unit);
        $sql = SQL::newUpdate('column');
        $sql->addUpdate('column_field_1', $unit);
        $sql->addWhereOpr('column_id', $utid);
        $sql->addWhereIn('column_blog_id', $targetBlogIds);
        DB::query($sql->get(dsn()), 'exec');

        $eid = ACMS_RAM::unitEntry($utid);
        if ($eid !== null) {
            Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        }

        return true;
    }

    /**
     * フィールドのテキスト置換
     *
     * @param string $type
     * @param string $identifer
     * @param string $pattern
     * @param string $replacement
     * @param int[] $targetBlogIds
     * @param string $filter
     * @return bool
     */
    protected function replaceFieldText(string $type, string $identifer, string $pattern, string $replacement, array $targetBlogIds, string $filter): bool
    {
        $identiferAry = preg_split('/:/', $identifer, 3);
        if (!$identiferAry) {
            return false;
        }
        [$id, $sort, $key] = array_pad($identiferAry, 2, '');
        if (empty($id) || empty($key)) {
            return false;
        }
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_value');
        $sql->addWhereOpr("field_{$type}", $id);
        $sql->addWhereOpr('field_sort', $sort);
        $sql->addWhereOpr('field_key', $key);
        if ($filter) {
            $sql->addWhereOpr('field_key', $filter);
        }
        $value = DB::query($sql->get(dsn()), 'one');

        if (empty($value)) {
            return false;
        }
        $value = preg_replace("@({$pattern})@iu", $replacement, $value);

        $sql = SQL::newUpdate('field');
        $sql->addUpdate('field_value', $value);
        $sql->addWhereOpr("field_{$type}", $id);
        $sql->addWhereOpr('field_sort', $sort);
        $sql->addWhereOpr('field_key', $key);
        if ($filter) {
            $sql->addWhereOpr('field_key', $filter);
        }
        $sql->addWhereIn('field_blog_id', $targetBlogIds);
        DB::query($sql->get(dsn()), 'exec');

        Common::deleteFieldCache($type, $id);
        if ($type === 'eid') {
            Common::saveFulltext('eid', $id, Common::loadEntryFulltext($id));
        } elseif ($type === 'unit_id') {
            $eid = (int) ACMS_RAM::unitEntry($id);
            Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        }
        return true;
    }
}
