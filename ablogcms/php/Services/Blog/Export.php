<?php

namespace Acms\Services\Blog;

use SQL;
use DB;
use Acms\Services\Facades\BlockEditor;
use Acms\Services\Contracts\Export as ExportBase;

class Export extends ExportBase
{
    /**
     * @var array
     */
    protected $tables;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var int[]
     */
    protected $mediaIds = [];

    public function __construct()
    {
        $this->setTables([
            'category',
            'column',
            'config',
            'comment',
            'config_set',
            'dashboard',
            'entry',
            'field',
            'form',
            'module',
            'rule',
            'tag',
            'schedule',
            'layout_grid',
            'blog',
        ]);
        $dsn = dsn();
        $this->prefix = $dsn['prefix'];
    }

    /**
     * エクスポートを実行
     *
     * @param resource $fp
     * @param int $bid
     * @return array<array{type: string, path: string}> エクスポートしたメディアで違うブログのメディアのパス
     */
    public function export($fp, int $bid): array
    {
        $queryList = [];
        foreach ($this->tables as $table) {
            $sql = SQL::newSelect($table);
            $sql->addWhereOpr($table . '_blog_id', $bid);
            $method = 'fixQuery' . ucfirst($table);
            $callback = [$this, $method];
            if (is_callable($callback)) {
                $sql = call_user_func_array($callback, [$sql, $bid]);
            }
            $q = $sql->get(dsn());
            $queryList[$table] = $q;
        }
        $this->dumpYaml($fp, $queryList);
        return $this->exportMedia($fp, $bid);
    }

    /**
     * メディアをエクスポート
     *
     * @param resource $fp
     * @return array<array{type: string, path: string}>  エクスポートしたメディアで違うブログのメディアのパス
     */
    protected function exportMedia($fp, int $bid): array
    {
        $mediaQueryList = [];
        // メディアデータ
        $sql = SQL::newSelect('media');
        $where = SQL::newWhere();
        $where->addWhereOpr('media_blog_id', $bid, '=', 'OR');
        $where->addWhereIn('media_id', $this->mediaIds, 'OR');
        $sql->addWhere($where);
        $mediaSelectQuery = $sql->get(dsn());
        $mediaQueryList['media'] = $mediaSelectQuery;
        // メディアタグデータ
        $sql = SQL::newSelect('media_tag');
        $where = SQL::newWhere();
        $where->addWhereOpr('media_tag_blog_id', $bid, '=', 'OR');
        $where->addWhereIn('media_tag_media_id', $this->mediaIds, 'OR');
        $sql->addWhere($where);
        $mediaQueryList['media_tag'] = $sql->get(dsn());

        $this->dumpYaml($fp, $mediaQueryList);

        $mediaPaths = [];
        $statement = DB::query($mediaSelectQuery, 'exec', false);

        while ($row = DB::next($statement)) {
            if (intval($row['media_blog_id']) !== $bid) {
                $mediaPaths[] = [
                    'type' => $row['media_type'],
                    'path' => $row['media_path'],
                ];
            }
        }
        return $mediaPaths;
    }

    /**
     * fix data
     *
     * @param array &$record
     * @param string $table
     *
     * @return void
     */
    protected function fix(&$record, $table)
    {
        if ($table === 'column' && 'text' === $record['column_type']) {
            $txt = $record['column_field_1'];
            $record['column_field_1'] = $this->fixNextLine($txt);
        }
        if ($table === 'schedule') {
            $this->fixSchedule($record);
        }
        if ($table === 'fulltext') {
            $this->fixFulltext($record);
        }
        foreach ($record as $key => $value) {
            $key = substr($key, strlen($table . '_'));
            if ($key === 'media_id') {
                $this->mediaIds[] = (int) $value;
            }
        }
        $callback = [$this, "{$table}ExtractMediaId"];
        if (is_callable($callback)) {
            $value = call_user_func_array($callback, [$record]);
        }
    }

    /**
     * ignore user fulltext
     *
     * @param $record
     */
    private function fixFulltext(&$record)
    {
        if (!empty($record['fulltext_uid'])) {
            $record = false;
        }
        $txt = $record['fulltext_value'];
        $record['fulltext_value'] = $this->fixNextLine($txt);
    }

    /**
     * ignore schedule data without base set
     *
     * @param $record
     */
    private function fixSchedule(&$record)
    {
        if (
            1
            and $record['schedule_year'] == 0000
            and $record['schedule_month'] == 00
        ) {
            // no touch
        } else {
            $record = false;
        }
    }

    /**
     * ユニットデータからゴミ箱のデータを除外
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryColumn($SQL, $bid = 0)
    {
        $columns = DB::query([
            'sql' => 'SHOW COLUMNS FROM ' . $this->prefix . 'column',
            'params' => []
        ], 'all');
        foreach ($columns as $column) {
            $SQL->addSelect($column['Field']);
        }
        $SQL->addLeftJoin('entry', 'column_entry_id', 'entry_id');
        $SQL->addWhereOpr('entry_status', 'trash', '<>');

        return $SQL;
    }

    /**
     * コメントデータからゴミ箱のデータを除外
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryComment($SQL, $bid = 0)
    {
        $columns = DB::query([
            'sql' => 'SHOW COLUMNS FROM ' . $this->prefix . 'comment',
            'params' => []
        ], 'all');
        foreach ($columns as $column) {
            $SQL->addSelect($column['Field']);
        }
        $SQL->addLeftJoin('entry', 'comment_entry_id', 'entry_id');
        $SQL->addWhereOpr('entry_status', 'trash', '<>');

        return $SQL;
    }

    /**
     * エントリーデータからゴミ箱のデータを除外
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryEntry($SQL, $bid = 0)
    {
        $SQL->addWhereOpr('entry_status', 'trash', '<>');

        return $SQL;
    }

    /**
     * フィールドデータからゴミ箱のデータを除外
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryField($SQL, $bid = 0)
    {
        $columns = DB::query([
            'sql' => 'SHOW COLUMNS FROM ' . $this->prefix . 'field',
            'params' => [],
        ], 'all');
        foreach ($columns as $column) {
            $SQL->addSelect($column['Field']);
        }
        $SQL->addLeftJoin('entry', 'field_eid', 'entry_id');

        $SUB = SQL::newWhere();
        $SUB->addWhereOpr('entry_status', 'trash', '<>', 'OR');
        $SUB->addWhereOpr('field_eid', null, '=', 'OR');
        $SQL->addWhere($SUB);

        return $SQL;
    }

    /**
     * フルテキストデータからゴミ箱のデータを除外
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryFulltext($SQL, $bid = 0)
    {
        $columns = DB::query([
            'sql' => 'SHOW COLUMNS FROM ' . $this->prefix . 'fulltext',
            'params' => [],
        ], 'all');
        foreach ($columns as $column) {
            $SQL->addSelect($column['Field']);
        }
        $SQL->addLeftJoin('entry', 'fulltext_eid', 'entry_id');
        $SQL->addWhereOpr('entry_status', 'trash', '<>');

        return $SQL;
    }

    /**
     * ブログデータを部分的エクスポートに修正
     * This method is called dynamically via call_user_func_array().
     *
     * @param \SQL_Select $SQL
     * @param int $bid
     * @return mixed
     */
    private function fixQueryBlog($SQL, $bid = 0)
    {
        $SQL = SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        $SQL->addSelect('blog_parent');
        $SQL->addSelect('blog_config_set_id');
        $SQL->addSelect('blog_config_set_scope');
        $SQL->addSelect('blog_theme_set_id');
        $SQL->addSelect('blog_theme_set_scope');
        $SQL->addSelect('blog_editor_set_id');
        $SQL->addSelect('blog_editor_set_scope');
        $SQL->addWhereOpr('blog_id', $bid);

        return $SQL;
    }

    /**
     * コンフィグテーブルからメディアIDを抽出
     * This method is called dynamically via call_user_func_array().
     *
     * @param array $record
     * @return void
     * @phpstan-ignore-next-line
     */
    private function configExtractMediaId(array $record): void
    {
        if ($record['config_key'] === 'media_banner_mid' && !is_null($record['config_value'])) {
            $this->mediaIds[] = (int) $record['config_value'];
        }
    }

    /**
     * ユニットテーブルからメディアIDを抽出
     * This method is called dynamically via call_user_func_array().
     *
     * @param array $record
     * @return void
     * @phpstan-ignore-next-line
     */
    private function columnExtractMediaId(array $record): void
    {
        if (strncmp($record['column_type'], 'custom', 6) === 0) {
            $data = acmsDangerUnserialize($record['column_field_6']);
            if ($data instanceof \Field) {
                foreach ($data->listFields() as $fd) {
                    foreach ($data->getArray($fd, true) as $i => $val) {
                        if (strpos($fd, '@media') !== false) {
                            $this->mediaIds[] = (int) $val;
                        }
                    }
                }
            }
        } elseif (
            strncmp($record['column_type'], 'media', 5) === 0 &&
            !is_null($record['column_field_1'])
        ) {
            $this->mediaIds[] = (int) $record['column_field_1'];
        } elseif (
            strncmp($record['column_type'], 'block-editor', 12) === 0 &&
            !is_null($record['column_field_1'])
        ) {
            $this->mediaIds = array_merge($this->mediaIds, BlockEditor::extractMediaId($record['column_field_1']));
        }
    }

    /**
     * フィールドテーブルからメディアIDを抽出
     * This method is called dynamically via call_user_func_array().
     *
     * @param array $record
     * @return void
     * @phpstan-ignore-next-line
     */
    private function fieldExtractMediaId(array $record): void
    {
        if (
            preg_match('/@media$/', $record['field_key']) &&
            !is_null($record['field_value'])
        ) {
            $this->mediaIds[] = (int) $record['field_value'];
        } elseif (
            $record['field_type'] === 'block-editor' &&
            !is_null($record['field_value'])
        ) {
            $this->mediaIds = array_merge($this->mediaIds, BlockEditor::extractMediaId($record['field_value']));
        }
    }
}
