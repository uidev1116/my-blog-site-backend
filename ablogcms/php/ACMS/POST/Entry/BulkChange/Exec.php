<?php

class ACMS_POST_Entry_BulkChange_Exec extends ACMS_POST_Entry_BulkChange_Confirm
{
    /**
     * Run
     *
     * @inheritDoc
     */
    public function post()
    {
        DB::setThrowException(true);
        try {
            $this->set();
            $this->validate();
            $this->bulkChange();

            AcmsLogger::info('エントリーの一括変更を行いました', [
                'eids' => implode(',', $this->targetEids),
                'action' => $this->entryActions,
                'entry' => Common::extract('entry'),
                'field' => Common::extract('field'),
            ]);

            $this->Post->set('step', '4');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_PermissionDenied $e) {
            AcmsLogger::info('権限がないため、エントリーの一括変更に失敗しました');
            die403('Permission denied.');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_TargetEmpty $e) {
            AcmsLogger::info('一括変更するエントリーが指定されていないため、処理を終了しました');
            $this->Post->set('step', '1');
            $this->Post->set('error', 'targetEmpty');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_OperationEmpty $e) {
            AcmsLogger::info('一括変更する内容が指定されていないため、エントリーの一括変更処理を終了しました');
            $this->Post->set('step', '2');
            $this->Post->set('error', 'operationEmpty');
        }
        DB::setThrowException(false);

        return $this->Post;
    }

    /**
     * Bulk change
     */
    protected function bulkChange()
    {
        set_time_limit(0);

        $this->changeEntry();
        $this->changeEntryField();

        foreach ($this->targetEids as $eid) {
            Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        }
    }

    /**
     * 部分的なエントリー情報を保存
     */
    protected function changeEntry()
    {
        $db = DB::singleton(dsn());
        $entry = Common::extract('entry');

        $sql = SQL::newUpdate('entry');
        foreach ($this->entryActions as $action) {
            $method = Common::camelize($action);
            if (method_exists($this, $method)) {
                $this->{$method}($sql, $entry); // @phpstan-ignore-line
            } else {
                $sql->addUpdate($action, $entry->get($action));
            }
        }
        if ($sql->_update) {
            $sql->addWhereIn('entry_id', $this->targetEids);
            $q = $sql->get(dsn());
            $db->query($q, 'exec');
        }

        foreach ($this->targetEids as $eid) {
            ACMS_RAM::entry($eid, null);
        }
    }


    protected function changeEntryField()
    {
        $field = Common::extract('field');
        $field->addField('updateField', 'on');
        foreach ($this->targetEids as $eid) {
            Common::saveField('eid', $eid, clone $field);
        }
    }

    protected function entrySubCategoryId($sql, $entry)
    {
        $cid = $entry->get('entry_category_id', null);
        foreach ($this->targetEids as $eid) {
            Entry::saveSubCategory($eid, $cid, $entry->get('entry_sub_category_id'));
        }
    }

    protected function entryTag($sql, $entry)
    {
        $tags = $entry->get('entry_tag');
        $db = DB::singleton(dsn());

        $sql = SQL::newDelete('tag');
        $sql->addWhereIn('tag_entry_id', $this->targetEids);
        $db->query($sql->get(dsn()), 'exec');

        if (!empty($tags)) {
            $tags = Common::getTagsFromString($tags);
            $sql = SQL::newBulkInsert('tag');
            foreach ($tags as $sort => $tag) {
                if (isReserved($tag)) {
                    continue;
                }
                foreach ($this->targetEids as $eid) {
                    $sql->addInsert([
                        'tag_name' => $tag,
                        'tag_sort' => $sort + 1,
                        'tag_entry_id' => $eid,
                        'tag_blog_id' => ACMS_RAM::entryBlog($eid),
                    ]);
                }
            }
            if ($sql->hasData()) {
                $db->query($sql->get(dsn()), 'exec');
            }
        }
    }
}
