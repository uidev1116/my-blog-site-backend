<?php

namespace Acms\Services\Database;

use DB;
use AcmsLogger;

class Replication
{
    /**
     * @var \Acms\Services\Database\Engine\Base
     */
    protected $db;

    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var array
     */
    protected $dsn;

    public function __construct($dsn = null)
    {
        if (empty($dsn)) {
            $this->dsn = dsn();
        }
        $this->dbName = $this->dsn['name'];
        $this->db = DB::singleton($this->dsn);
    }

    /**
     * 全テーブルの取得
     *
     * @return array
     */
    public function getTableList()
    {
        $sql = [
            'sql' => 'SHOW TABLES FROM `' . $this->dbName . '`',
            'params' => []
        ];
        $tables = DB::query($sql, 'all');

        $list = [];
        foreach ($tables as $key => $table) {
            array_push($list, strtolower(reset($table)));
        }

        return $list;
    }

    /**
     * 全テーブルの削除
     *
     * @return void
     */
    public function dropAllTables()
    {
        $list = [];
        foreach ($this->getTableList() as $table) {
            $table = strtolower($table);
            array_push($list, '`' . $table . '`');
        }
        $tables_str = implode(', ', $list);
        $sql = [
            'sql' => 'DROP TABLE ' . $tables_str,
            'params' => [],
        ];
        $sql2 = [
            'sql' => 'DROP TABLE ' . strtoupper($tables_str),
            'params' => [],
        ];
        DB::query($sql, 'exec');
        DB::query($sql2, 'exec');
    }

    /**
     * 全テーブルのリネーム
     *
     * @return void
     */
    public function renameAllTable()
    {
        $list = [];
        foreach ($this->getTableList() as $table) {
            $table = strtolower($table);
            if (!preg_match('/^backup_acms_.*/', $table) and preg_match('/^' . DB_PREFIX . '.*/', $table)) {
                array_push($list, $table . ' TO backup_acms_' . $table);
            }
        }
        $tables_str = implode(', ', $list);
        $sql = [
            'sql' => 'RENAME TABLE ' . $tables_str,
            'params' => [],
        ];

        DB::query($sql, 'exec');
    }

    /**
     * 一時テーブルの削除
     *
     * @return void
     */
    public function dropCashTable()
    {
        $list = [];
        foreach ($this->getTableList() as $table) {
            $table = strtolower($table);
            if (preg_match('/^backup_acms_.*/', $table)) {
                array_push($list, '`' . $table . '`');
            }
        }
        $tables_str = implode(', ', $list);
        if (!empty($tables_str)) {
            $sql = [
                'sql' => 'DROP TABLE ' . $tables_str,
                'params' => [],
            ];
            $sql2 = [
                'sql' => 'DROP TABLE ' . strtoupper($tables_str),
                'params' => [],
            ];
            try {
                DB::query($sql, 'exec');
            } catch (\Exception $e) {
                AcmsLogger::notice($e->getMessage());
            }
            try {
                DB::query($sql2, 'exec');
            } catch (\Exception $e) {
                AcmsLogger::notice($e->getMessage());
            }
        }
    }

    /**
     * テーブル作成クエリの組み立て
     *
     * @return string
     */
    public function buildCreateTableSql()
    {
        $master = '';
        $list = [];
        foreach ($this->getTableList() as $table) {
            $table = strtolower($table);
            if (!preg_match('/^backup_acms_.*/', $table) and preg_match('/^' . DB_PREFIX . '.*/', $table)) {
                array_push($list, $table);
            }
        }

        foreach ($list as $key => $row) {
            $sql = [
                'sql' => 'SHOW CREATE TABLE ' . $row,
                'params' => [],
            ];
            $create = DB::query($sql, 'all');
            foreach ($create as $createRow) {
                $create_sql = $createRow['Create Table'];
                $create_sql = str_replace(["\r\n", "\n", "\r"], '', $create_sql);
                $master .= $create_sql . ';' . PHP_EOL;
            }
        }

        return $master;
    }

    /**
     * データ投入sqlの組み立て
     *
     * @param string $table
     * @param resource $handle
     *
     * @return void
     */
    public function buildInsertSql($table, &$handle)
    {
        if (preg_match('/^backup_acms_.*/', $table)) {
            return;
        }
        $db = DB::singleton(dsn());
        $columnsList = [];
        $columnsType = [];

        $columns = $db->query([
            'sql' => 'SHOW COLUMNS FROM `' . $table . '`',
            'params' => []
        ], 'all');
        foreach ($columns as $row) {
            $name = $row['Field'];
            array_push($columnsList, $name);
            $columnsType[$name] = $row['Type'];
        }
        $q = [
            'sql' => "SELECT * FROM $table",
            'params' => [],
        ];
        $all = $db->query($q, 'all', false);

        foreach ($all as $row) {
            $masterQuery = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columnsList) . '`) VALUES ';
            $masterQuery .= '(';
            $j = 0;
            foreach ($columnsType as $name => $type) {
                $type = strtolower($type);
                if ($j !== 0) {
                    $masterQuery .= ', ';
                }
                $value = $row[$name];
                if ($value === null) {
                    $masterQuery .= 'NULL';
                } else {
                    if (preg_match('/(blob|binary|point|geometry)/', $type) || false === detectEncode($value)) {
                        $value = 'X\'' . bin2hex($value) . '\'';
                    } else {
                        $value = DB::quote($value);
                    }
                    $masterQuery .= $value;
                }
                $j++;
            }
            $masterQuery .= ');' . PHP_EOL;
            $masterQuery = preg_replace('/' . DB_PREFIX . '/', 'DB_PREFIX_STR_', $masterQuery);
            if ('UTF-8' !== DB_CHARSET) {
                if (!is_string($masterQuery)) {
                    return;
                }
                $val = mb_convert_encoding($masterQuery, "UTF-8", DB_CHARSET);
                if ($val === false) {
                    throw new \RuntimeException('mb_convert_encoding failed. ' . DB_CHARSET . ' -> UTF-8');
                }
                if ($masterQuery === mb_convert_encoding($val, DB_CHARSET, 'UTF-8')) {
                    $masterQuery = $val;
                }
            }
            if ($masterQuery) {
                fwrite($handle, $masterQuery);
            }
        }
    }

    /**
     * ドメインの書き換え
     *
     * @param string $new_domain
     * @param string $name
     *
     * @return void
     */
    public function rewriteDomain($new_domain, $name)
    {
        $sql = [
            'sql' => 'UPDATE ' . $name . ' SET blog_domain=' . DB::quote($new_domain),
            'params' => [],
        ];
        DB::query($sql, 'exec');
    }

    /**
     * @throws \RuntimeException
     */
    public function authorityValidation()
    {
        $table = 'TEMP_' . date('yMd_His');
        $new_table = 'R_' . $table;

        if (!DB::query(['sql' => 'CREATE TABLE `' . $table . '` (test VARCHAR(1))', 'params' => []], 'exec')) {
            throw new \RuntimeException('CREATE TABLE権限がありません。 ' . implode(' ', DB::errorInfo()));
        }

        if (!DB::query(['sql' => 'RENAME TABLE `' . $table . '` TO `' . $new_table . '`', 'params' => []], 'exec')) {
            throw new \RuntimeException('RENAME TABLEする権限がありません。 ' . implode(' ', DB::errorInfo()));
        }

        if (!DB::query(['sql' => 'DROP TABLE `' . $new_table . '`', 'params' => []], 'exec')) {
            throw new \RuntimeException('DROP TABLEする権限がありません。 ' . implode(' ', DB::errorInfo()));
        }

        if (!DB::query(['sql' => 'SHOW TABLES FROM `' . DB_NAME . '`', 'params' => []], 'exec')) {
            throw new \RuntimeException('SHOW TABLESする権限がありません。 ' . implode(' ', DB::errorInfo()));
        }
    }
}
