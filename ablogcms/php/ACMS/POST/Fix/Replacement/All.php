<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database as DB;

class ACMS_POST_Fix_Replacement_All extends ACMS_POST_Fix
{
    public function post()
    {
        $field = $this->extract('fix');
        if (!$this->validate($field)) {
            return $this->Post;
        }
        [$target, $pattern, $replacement, $filter] = $this->getReplaceSetting($field);
        $targetBlogIds = $this->targetBlog($field->get('fix_replacement_target_blog') === 'descendant');

        $this->replaceAll($target, $pattern, $replacement, $targetBlogIds, $filter);
        $updated = DB::affected_rows();

        $this->completeProcess($updated);
        $this->saveLog($target, $updated, $pattern, $replacement);

        return $this->Post;
    }

    /**
     * 一括テキスト置換を実行
     *
     * @param string $target
     * @param string $pattern
     * @param string $replacement
     * @param int[] $targetBlogIds
     * @param string $filter
     * @return void
     */
    public function replaceAll(string $target, string $pattern, string $replacement, array $targetBlogIds, string $filter = ''): void
    {
        switch ($target) {
            case 'title':
                $rep = SQL::newFunction('entry_title', ['REPLACE', $pattern, $replacement]);
                $sql = SQL::newUpdate('entry');
                $sql->addUpdate('entry_title', $rep);
                $sql->addWhereOpr('entry_title', "%{$pattern}%", 'LIKE');
                $sql->addWhereIn('entry_blog_id', $targetBlogIds);
                DB::query($sql->get(dsn()), 'exec');
                break;
            case 'unit':
                $rep = SQL::newFunction('column_field_1', ['REPLACE', $pattern, $replacement]);
                $sql = SQL::newUpdate('column');
                $sql->addUpdate('column_field_1', $rep);
                $sql->addWhereOpr('column_field_1', "%{$pattern}%", 'LIKE');
                $sql->addWhereIn('column_blog_id', $targetBlogIds);
                DB::query($sql->get(dsn()), 'exec');
                break;
            case 'custom_unit':
                $rep = SQL::newFunction('field_value', ['REPLACE', $pattern, $replacement]);
                $sql = SQL::newUpdate('field');
                $sql->addUpdate('field_value', $rep);
                $sql->addWhereOpr('field_unit_id', null, '<>');
                $sql->addWhereOpr('field_value', "%{$pattern}%", 'LIKE');
                if ($filter) {
                    $sql->addWhereOpr('field_key', $filter);
                }
                $sql->addWhereIn('field_blog_id', $targetBlogIds);
                DB::query($sql->get(dsn()), 'exec');
                break;
            case 'field':
                $rep = SQL::newFunction('field_value', ['REPLACE', $pattern, $replacement]);
                $sql = SQL::newUpdate('field');
                $sql->addUpdate('field_value', $rep);
                $sql->addWhereOpr('field_eid', null, '<>');
                $sql->addWhereOpr('field_value', "%{$pattern}%", 'LIKE');
                if ($filter) {
                    $sql->addWhereOpr('field_key', $filter);
                }
                $sql->addWhereIn('field_blog_id', $targetBlogIds);
                DB::query($sql->get(dsn()), 'exec');
                break;
        }
    }

    /**
     * 完了後の処理
     *
     * @param int $updated
     * @return void
     */
    protected function completeProcess(int $updated): void
    {
        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_id');
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');
        while ($row = DB::next($statement)) {
            $eid = $row['entry_id'];
            Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
            Common::deleteFieldCache('eid', $eid);
        }

        parent::completeProcess($updated);
    }
}
