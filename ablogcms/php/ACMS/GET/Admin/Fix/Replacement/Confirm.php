<?php

use Acms\Services\Facades\Database as DB;

class ACMS_GET_Admin_Fix_Replacement_Confirm extends ACMS_GET_Admin_Fix
{
    private $limit;

    public function select_title($word, $includeDescendant = false)
    {
        $DB = DB::singleton(dsn());

        if (empty($word)) {
            return [];
        }
        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_id');
        $SQL->addSelect('entry_title');
        $SQL->addWhereOpr('entry_title', '%' . $word . '%', 'LIKE');
        if ($includeDescendant) {
            $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
            ACMS_Filter::blogTree($SQL, BID, 'descendant-or-self');
        } else {
            $SQL->addWhereOpr('entry_blog_id', BID);
        }

        $all = $DB->query($SQL->get(dsn()), 'all');
        $list = [];
        foreach ($all as $row) {
            $list[] = [
                'id' => $row['entry_id'],
                'text' => $row['entry_title'],
                'eid' => $row['entry_id'],
            ];
        }
        return $list;
    }

    public function select_text_unit($word, $includeDescendant = false)
    {
        $DB = DB::singleton(dsn());

        if (empty($word)) {
            return [];
        }
        $SQL = SQL::newSelect('column');
        $SQL->addWhereOpr('column_field_1', '%' . $word . '%', 'LIKE');
        if ($includeDescendant) {
            $SQL->addLeftJoin('blog', 'blog_id', 'column_blog_id');
            ACMS_Filter::blogTree($SQL, BID, 'descendant-or-self');
        } else {
            $SQL->addWhereOpr('column_blog_id', BID);
        }

        $all = $DB->query($SQL->get(dsn()), 'all');
        $list = [];
        foreach ($all as $row) {
            $list[] = [
                'id' => $row['column_id'],
                'text' => $row['column_field_1'],
                'eid' => $row['column_entry_id'],
            ];
        }
        return $list;
    }

    public function select_custom_unit($word, $filter, $includeDescendant = false)
    {
        $DB = DB::singleton(dsn());

        if (empty($word)) {
            return [];
        }
        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_sort');
        $SQL->addSelect('field_unit_id');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_unit_id', null, '<>');
        $SQL->addWhereOpr('field_value', '%' . $word . '%', 'LIKE');
        if (!empty($filter)) {
            $SQL->addWhereOpr('field_key', $filter);
        }
        if ($includeDescendant) {
            $SQL->addLeftJoin('blog', 'blog_id', 'field_blog_id');
            ACMS_Filter::blogTree($SQL, BID, 'descendant-or-self');
        } else {
            $SQL->addWhereOpr('field_blog_id', BID);
        }

        $all = $DB->query($SQL->get(dsn()), 'all');
        $list = [];
        foreach ($all as $row) {
            $list[] = [
                'id' => $row['field_unit_id'] . ':' . $row['field_sort'] . ':' . $row['field_key'],
                'text' => $row['field_value'],
                'eid' => ACMS_RAM::unitEntry($row['field_unit_id']),
                'key' => $row['field_key'],
            ];
        }
        return $list;
    }

    public function select_customfield($word, $filter, $includeDescendant = false)
    {
        $DB = DB::singleton(dsn());

        if (empty($word)) {
            return [];
        }
        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_sort');
        $SQL->addSelect('field_eid');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_eid', null, '<>');
        $SQL->addWhereOpr('field_value', '%' . $word . '%', 'LIKE');
        if (!empty($filter)) {
            $SQL->addWhereOpr('field_key', $filter);
        }
        if ($includeDescendant) {
            $SQL->addLeftJoin('blog', 'blog_id', 'field_blog_id');
            ACMS_Filter::blogTree($SQL, BID, 'descendant-or-self');
        } else {
            $SQL->addWhereOpr('field_blog_id', BID);
        }

        $all = $DB->query($SQL->get(dsn()), 'all');
        $list = [];
        foreach ($all as $row) {
            $list[] = [
                'id' => $row['field_eid'] . ':' . $row['field_sort'] . ':' . $row['field_key'],
                'text' => $row['field_value'],
                'eid' => $row['field_eid'],
                'key' => $row['field_key'],
            ];
        }
        return $list;
    }

    public function get()
    {
        if (!sessionWithAdministration()) {
            return '';
        }

        @set_time_limit(0);

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $step = $this->Post->get('step');

        $Fix = $this->Post->getChild('fix');
        $target = $Fix->get('fix_replacement_target');
        $pattern = $Fix->get('fix_replacement_pattern');
        $filter = $Fix->get('fix_replacement_target_cf_filter');
        $includeDescendant = $Fix->get('fix_replacement_target_blog') === 'descendant';
        $this->limit = $Fix->get('fix_replacement_limit', 100);

        if ($step !== 'confirm') {
            return '';
        }

        $list = [];

        switch ($target) {
            case 'title':
                $list = $this->select_title($pattern, $includeDescendant);
                break;
            case 'unit':
                $list = $this->select_text_unit($pattern, $includeDescendant);
                break;
            case 'custom_unit':
                $list = $this->select_custom_unit($pattern, $filter, $includeDescendant);
                $Tpl->add('field_name');
                break;
            case 'field':
                $list = $this->select_customfield($pattern, $filter, $includeDescendant);
                $Tpl->add('field_name');
                break;
            default:
                return '';
        }

        if (empty($list)) {
            $Tpl->add('notFound');

            return $Tpl->get();
        }

        foreach ($list as $row) {
            $id = $row['id'];
            $eid = $row['eid'];
            $hits = $row['text'];
            $hits = preg_replace('/(' . preg_quote($pattern, '/') . ')/iu', '<strong class="highlight1">$1</strong>', $hits);

            $loop = [
                'id' => $id,
                'text' => $hits,
                'url' => acmsLink([
                    'eid' => $eid,
                ]),
            ];
            if (isset($row['key'])) {
                $loop['key'] = $row['key'];
            }
            $Tpl->add('found:loop', $loop);
        }

        return $Tpl->get();
    }
}
