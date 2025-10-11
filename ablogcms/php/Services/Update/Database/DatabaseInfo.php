<?php

namespace Acms\Services\Update\Database;

use DB;

class DatabaseInfo
{
    /**
     * DB接続情報
     *
     * @var array
     */
    protected $dsn;

    /**
     * DatabaseInfo constructor.
     *
     * @param $dsn array
     */
    public function __construct($dsn)
    {
        $this->dsn = $dsn;
    }

    /**
     * テーブル一覧の取得
     *
     * @return array
     */
    public function getTables()
    {
        $DB = DB::singleton($this->dsn);
        $q = [
            'sql' => "SHOW TABLES FROM `" . $this->dsn['name'] . "` LIKE :table_prefix",
            'params' => [
                'table_prefix' => $this->dsn['prefix'] . '%'
            ],
        ];
        $all = $DB->query($q, 'all');
        $tables = [];
        foreach ($all as $tb) {
            $tables[] = implode($tb);
        }
        return $tables;
    }

    /**
     * テーブルのカラム一覧の取得
     *
     * @param $table string
     * @return array
     */
    public function getColumns($table)
    {
        $DB = DB::singleton($this->dsn);
        $q = [
            'sql' => "SHOW COLUMNS FROM `{$table}`",
            'params' => [],
        ];
        $all = $DB->query($q, 'all');
        $columns = [];
        foreach ($all as $fd) {
            $columns[$fd['Field']] = $fd;
        }
        return $columns;
    }

    /**
     * テーブルのインデックスの取得
     *
     * @param $table string
     * @return array
     */
    public function getIndex($table)
    {
        $DB = DB::singleton($this->dsn);
        $q = [
            'sql' => "SHOW INDEX FROM `{$table}`",
            'params' => [],
        ];
        $all = $DB->query($q, 'all');
        $index = [];
        foreach ($all as $fd) {
            $index[] = $fd['Key_name'];
        }
        $index = array_values(array_unique($index));
        return $index;
    }

    /**
     * カラムのリネーム
     *
     * @param string $table
     * @param string $left
     * @param array $def
     * @param string $right
     */
    public function rename($table, $left, $def, $right)
    {
        $this->alterTable('rename', $table, $left, $def, $right);
    }

    /**
     * テーブルのEngineを変更
     *
     * @param string $table
     * @param string $engine
     */
    public function changeEngine($table, $engine)
    {
        $DB = DB::singleton($this->dsn);
        $sql = [
            'sql' => "SELECT ENGINE FROM information_schema.tables WHERE table_schema = :table_schema AND table_name = :table_name",
            'params' => [
                'table_name' => $table,
                'table_schema' => $this->dsn['name'],
            ],
        ];
        $current = $DB->query($sql, 'one');

        if ($current === $engine) {
            return;
        }
        $this->alterTable('engine', $table, $engine);
    }

    /**
     * カラムの追加
     *
     * @param string $table
     * @param string $left
     * @param array $def
     * @param string $after
     */
    public function add($table, $left, $def, $after)
    {
        $this->alterTable('add', $table, $left, $def, $after);
    }

    /**
     * カラムの変更
     *
     * @param string $table
     * @param string $left
     * @param array $def
     */
    public function change($table, $left, $def)
    {
        $this->alterTable('change', $table, $left, $def);
    }

    /**
     * 現在のインデックスを取得
     *
     * @param string $table
     * @return array
     */
    public function showIndex($table)
    {
        $DB = DB::singleton($this->dsn);
        $q = [
            'sql' => "SHOW INDEX FROM `$table`",
            'params' => [],
        ];
        $all = $DB->query($q, 'all');
        $fds = [];
        foreach ($all as $fd) {
            $fds[] = $fd;
        }
        return $fds;
    }

    /**
     * _alterTable カラム定義の変更を適用する
     *
     * @param string $method
     * @param string $tb
     * @param string $left
     * @param array $def カラム定義
     * @param string $right
     * @return void
     */
    protected function alterTable($method, $tb, $left, $def = [], $right = null)
    {
        $q = "ALTER TABLE `$tb`";

        $def['Null'] = (isset($def['Null']) && $def['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
        $def['Default'] = !empty($def['Default']) ? "default '" . $def['Default']  . "'" : null;
        $def['Extra'] = isset($def['Extra']) ? ' ' . $def['Extra'] : '';

        switch ($method) {
            case 'add':
                $q .= " ADD";
                $q .= " `" . $left . "` " . $def['Type'] . " " . $def['Null'] . " " . $def['Default'] . $def['Extra'] . " AFTER " . " `" . $right . "`";
                break;
            case 'change':
                // カラムのサイズ変更で現行サイズより小さい場合は処理をスキップ
                if (preg_match('/^[a-z]+\((\d+)\)/', $def['Type'], $match)) {
                    $cq = [
                        'sql' => "SHOW COLUMNS FROM " . $tb . " LIKE '" . $left . "'",
                        'params' => [],
                    ];
                    $DB = DB::singleton($this->dsn);
                    $all = $DB->query($cq, 'all');
                    $size = $match[1];

                    foreach ($all as $row) {
                        $type = $row['Type'];
                        if (preg_match('/^[a-z]+\((\d+)\)/', $type, $match)) {
                            $csize = $match[1];
                            if (intval($size) < intval($csize)) {
                                break;
                            }
                        }
                    }
                }
                $q .= " CHANGE";
                $q .= " `" . $left . "` `" . $left . "` " . $def['Type'] . " " . $def['Null'] . " " . $def['Default'] . $def['Extra'];
                break;
            case 'rename':
                $q .= " CHANGE";
                $q .= " `" . $left . "` `" . $right . "` " . $def['Type'] . " " . $def['Null'] . " " . $def['Default'] . $def['Extra'];
                break;
            case 'engine':
                $q .= " ENGINE=";
                $q .= $left;
                break;
            case 'drop':
                $q .= " DROP";
                $q .= " `" . $left . "`";
        }
        $DB = DB::singleton($this->dsn);
        $DB->query([
            'sql' => $q,
            'params' => [],
        ], 'exec');
    }

    /**
     * テーブルを作成する
     *
     * @param array $tables
     * @param array|null $idx
     *
     * @throws \RuntimeException
     */
    public function createTables($tables, $idx = null, $define = [])
    {
        foreach ($tables as $tb) {
            $def = $define[$tb];

            $q = "CREATE TABLE {$tb} ( \r\n";
            foreach ($def as $row) {
                $row['Null'] = (isset($row['Null']) && $row['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
                $row['Default'] = !empty($row['Default']) ? "default '" . $row['Default'] . "'" : null;

                // Example: field_name var_type(11) NOT NULL default HOGEHOGE,\r\n
                $q .= $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Default']  .  ' ' . $row['Extra'] . ",\r\n";
            }

            /**
             * if $idx is exists Generate KEYs
             */
            if (is_array($idx) && !empty($idx) && isset($idx[$tb])) {
                $keys = $idx[$tb];
                if (is_array($keys) && !empty($keys)) {
                    foreach ($keys as $key) {
                        $q .= $key . ",\r\n";
                    }
                }
            }
            $q = preg_replace('@,(\r\n)$@', '$1', $q);
            if (preg_match('/(fulltext|geo)$/', $tb)) {
                $q .= ") ENGINE=MyISAM;";
            } else {
                $q .= ") ENGINE=InnoDB;";
            }

            $sql = [
                'sql' => $q,
                'params' => [],
            ];
            $DB = DB::singleton($this->dsn);
            $isSuccess = $DB->query($sql, 'exec');
            if ($isSuccess === false) {
                throw new \RuntimeException('「' . $tb . '」' . 'テーブルの作成に失敗しました。');
            }
        }
    }
}
