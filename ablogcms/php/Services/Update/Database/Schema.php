<?php

namespace Acms\Services\Update\Database;

use Config;
use DB;

class Schema
{
    /**
     * 現在のテーブル情報
     *
     * @var array
     */
    public $currentTableInfo;

    /**
     * 新しいデータベース定義
     *
     * @var array
     */
    public $define;

    /**
     * 現在のテーブル定義
     * @var array
     */
    public $schema;

    /**
     * 新しいデータベースのインデックス情報
     * @var array
     */
    public $indexDefine;

    /**
     * 現在のインデックス情報
     * @var mixed
     */
    public $dbIndex;

    /**
     * DB接続情報
     *
     * @var array
     */
    protected $dsn;

    /**
     * 新しいデータベースのリネーム情報
     *
     * @var array
     */
    protected $renameDefine;

    /**
     * 新しいデータベースのEngine変更情報
     */
    protected $engineDefine;

    /**
     * @var \Acms\Services\Update\Database\DatabaseInfo
     */
    protected $dbInfo;

    /**
     * Schema constructor.
     *
     * @param $dsn
     */
    public function __construct($dsn)
    {
        $this->dsn = $dsn;
        $this->dbInfo = new DatabaseInfo($dsn);
        $this->setSchema();
    }

    /**
     * データベーススキーマを再セット
     */
    public function setSchema()
    {
        $this->schema = $this->getDatabaseDefinitionCurrent();
        $this->dbIndex = $this->getDatabaseIndexCurrent();
        $this->define = Config::getDataBaseSchemaInfo('schema');
        $this->renameDefine = Config::getDataBaseSchemaInfo('rename');
        $this->engineDefine = Config::getDataBaseSchemaInfo('engine');
        $this->indexDefine = Config::getDataBaseSchemaInfo('index');

        if (!empty($this->define[0])) {
            unset($this->define[0]);
        }
    }

    /**
     * 現在のDBと定義を比較して，差分のテーブル名を配列で返す
     *
     * @return array
     */
    public function compareTables()
    {
        $now_tbs = $this->listUp($this->schema);
        $def_tbs = $this->listUp($this->define);

        $haystack = [];
        foreach ($def_tbs as $tb) {
            if (array_search($tb, $now_tbs, true) === false) {
                $haystack[] = $tb;
            }
        }
        return $haystack;
    }

    /**
     * カラム定義の違いを走査
     *
     * @param string $table
     *
     * @return mixed
     */
    public function compareColumns($table)
    {
        $now = null;
        $def = null;
        $addRam = [];
        $changeRam = [];

        if (isset($this->schema[$table])) {
            $now = $this->schema[$table];
        }
        if (isset($this->define[$table])) {
            $def = $this->define[$table];
        }

        if (empty($def)) {
            return [
                'add' => [],
                'change' => [],
            ];
        }

        $defineFields = $this->listUp($def);

        foreach ($defineFields as $key) {
            /**
             * ALTER TABLE ADD
             * is not exists living list
             */
            if (empty($now[$key])) {
                $addRam[] = $key;
                continue;
            }

            /**
             * ALTER TABLE CHANGE
             * is not equal column
             *   *
             * EXCEPT INDEX KEY
             */
            unset($now[$key]['key']);
            unset($def[$key]['key']);

            if ($now[$key] != $def[$key]) {
                $changeRam[] = $key;
                continue;
            }
        }

        return [
            'add' => $addRam,
            'change' => $changeRam,
        ];
    }

    /**
     * インデックス定義の違いを走査
     *
     * @param string $table
     *
     * @return mixed
     */
    public function compareIndex($table)
    {
        $currentIndex = [];
        $updateIndex = [];
        $resultIndex = [];
        if (isset($this->dbIndex[$table])) {
            $currentIndex = $this->dbIndex[$table];
        }
        if (isset($this->indexDefine[$table])) {
            $updateIndex = $this->indexDefine[$table];
        }
        foreach ($updateIndex as $new) {
            foreach ($currentIndex as $old) {
                $key = "/KEY\s+$old(\s|\()/i";
                if (preg_match($key, $new)) {
                    continue 2;
                }
                if ($old === 'PRIMARY' && preg_match('/PRIMARY/i', $new)) {
                    continue 2;
                }
            }
            $resultIndex[] = $new;
        }
        return $resultIndex;
    }

    /**
     * system table を更新できるか確認
     *
     * @return bool
     */
    public function checkAlterSystemTablePermission()
    {
        $DB = DB::singleton($this->dsn);
        $q = "ALTER TABLE `" . DB_PREFIX . "sequence` CHANGE `sequence_system_version` `sequence_system_version` VARCHAR(32) NOT NULL";
        $sql = [
            'sql' => $q,
            'params' => [],
        ];
        $res = $DB->query($sql, 'exec');

        return $res;
    }

    /**
     * テーブルを作成する
     *
     * @param string[] $tables
     * @param array|null $idx
     */
    public function createTables($tables, $idx = null)
    {
        $this->dbInfo->createTables($tables, $idx, $this->define);
        $this->reloadSchema();
    }

    /**
     * 名前に変更のあったフィールドを解決する
     */
    public function resolveRenames()
    {
        if (empty($this->renameDefine)) {
            return;
        }

        foreach ($this->renameDefine as $table => $field) {
            if (empty($table) || empty($field)) {
                continue;
            }
            $rename = $this->listUp($this->schema[$table]);

            foreach ($field as $k => $v) {
                if (in_array($k, $rename, true)) {
                    $val = $this->define[$table][$v];
                    $this->dbInfo->rename($table, $k, $val, $v);
                }
            }
        }

        $this->reloadSchema();
    }

    /**
     * テーブルのEngineを解決する
     */
    public function resolveEngines()
    {
        if (empty($this->engineDefine)) {
            return;
        }
        foreach ($this->engineDefine as $table => $engine) {
            if (empty($table) || empty($engine)) {
                continue;
            }
            $this->dbInfo->changeEngine($table, $engine);
        }
    }

    /**
     * compareColumns走査済みのすべてのカラムを追加・変更する
     *
     * @param string $table
     * @param array $add
     * @param array $change
     */
    public function resolveColumns($table, $add, $change)
    {
        $def = null;
        if (isset($this->define[$table])) {
            $def = $this->define[$table];
        }

        $list = $this->listUp($def);

        /**
         * ADD
         */
        if (!empty($add)) {
            foreach ($add as $key) {
                $after = array_slice($list, 0, (array_search($key, $list, true)));
                $after = end($after);
                $this->dbInfo->add($table, $key, $def[$key], $after);
            }
        }

        /**
         * CHANGE
         */
        if (!empty($change)) {
            foreach ($change as $key) {
                $this->dbInfo->change($table, $key, $def[$key]);
            }
        }

        $this->reloadSchema();
    }

    /**
     * 定義外の未使用カラムを走査
     */
    public function unusedColumns($table)
    {
        $now = null;
        $def = null;

        if (isset($this->schema[$table])) {
            $now = $this->schema[$table];
        }
        if (isset($this->define[$table])) {
            $def = $this->define[$table];
        }

        $columns = $this->listUp($now);
        $unused = [];
        foreach ($columns as $key) {
            /**
             * ALTER TABLE DROP ( TEMP )
             * is not exists defined list
             */
            if (empty($def[$key])) {
                $unused[] = $key;
                continue;
            }
        }
        return $unused;
    }

    /**
     * 指定されたテーブルのインデックスをすべて削除する
     *
     * @param string $table
     */
    public function clearIndex($table)
    {
        $index = $this->dbInfo->showIndex($table);
        if (empty($index)) {
            return;
        }

        $ary = [];
        foreach ($index as $idx) {
            $name = $idx['Key_name'];
            $ary[$name] = $name;
        }

        foreach ($ary as $key) {
            $DB = DB::singleton($this->dsn);
            $sql = [
                'sql' => "ALTER TABLE `$table` DROP INDEX `$key`",
                'params' => []
            ];
            $DB->query($sql, 'exec');
        }
    }

    /**
     * インデックスを作成する
     *
     * @param $table
     * @param $res
     */
    public function makeIndex($table, $res)
    {
        if (empty($res)) {
            return;
        }
        $DB = DB::singleton($this->dsn);
        foreach ($res as $index) {
            if (!preg_match('/^PRIMARY\sKEY/', $index)) {
                $sql = [
                    'sql' => "ALTER TABLE `$table` ADD $index",
                    'params' => []
                ];
                $DB->query($sql, 'exec');
            }
        }
    }

    /**
     * 配列のキーを返す・空配列は除かれる
     *
     * @param  $ary
     * @return array
     */
    public function listUp($ary)
    {
        if (empty($ary)) {
            return [];
        }

        return array_merge(array_diff(array_keys($ary), ['']));
    }

    /**
     * reload schema
     */
    protected function reloadSchema()
    {
        $this->schema = $this->getDatabaseDefinitionCurrent();
    }

    /**
     * 現在のデータベース定義を取得
     *
     * @return array
     */
    protected function getDatabaseDefinitionCurrent()
    {
        $tables = $this->dbInfo->getTables();
        if (!is_array($tables)) {
            return [];
        }

        $def = [];
        foreach ($tables as $table) {
            $columns = $this->dbInfo->getColumns($table);
            $def[$table] = $columns;
        }
        return $def;
    }

    /**
     * 現在のデータベースインデックス定義を取得
     *
     * @return array
     */
    protected function getDatabaseIndexCurrent()
    {
        $tables = $this->dbInfo->getTables();
        if (!is_array($tables)) {
            return [];
        }

        $def = [];
        foreach ($tables as $table) {
            $columns = $this->dbInfo->getIndex($table);
            $def[$table] = $columns;
        }
        return $def;
    }

    /**
     * データベース定義をYAMLからロードする
     *
     * @param $yaml string
     * @return mixed
     */
    protected function getDatabaseDefinitionForYaml($yaml)
    {
        return Config::yamlParse(str_replace('%{PREFIX}', DB_PREFIX, $yaml));
    }
}
