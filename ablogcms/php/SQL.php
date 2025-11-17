<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Doctrine\DBAL\Connection;

/**
 * SQL
 *
 * SQLヘルパのメソッド群です。
 *
 * @package php
 */
class SQL
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected static $connection = null;

    protected $result = null;

    /**
     * @param SQL|null $SQL
     */
    public function __construct($SQL = null)
    {
        if (SQL::isClass($SQL, 'SQL')) {
            foreach (get_object_vars($SQL) as $key => $value) {
                $this->$key = $value; // @phpstan-ignore-line
            }
        }
        if (self::$connection === null) {
            self::$connection = DriverManager::getConnection(array_filter([
                'driver' => 'pdo_mysql',
                'dbname' => DB_NAME,
                'user' => DB_USER,
                'password' => DB_PASS,
                'host' => DB_HOST,
                'port' => DB_PORT,
            ], fn($value) => $value !== null && $value !== ''));
        }
    }

    /**
     * データベース接続を設定するメソッドです。
     *
     * @param Connection $connection
     * @return void
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * SQL文とパラメータを取得するメソッドです。
     *
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>
     * }
     */
    public function get($dsn = null)
    {
        throw new Exception('SQL::get() is not implemented.');
    }

    /**
     * SQL文を取得するメソッドです。
     *
     * @param Dsn|null $dsn
     * @return string
     */
    public function getSQL($dsn = null): string
    {
        if ($this->result === null) {
            $this->result = $this->get($dsn);
        }
        return $this->result['sql'] ?? '';
    }

    /**
     * SQL文のパラメータを取得するメソッドです。
     *
     * @param Dsn|null $dsn
     * @return array<string, mixed>
     */
    public function getParams($dsn = null): array
    {
        if ($this->result === null) {
            $this->result = $this->get($dsn);
        }
        return $this->result['params'] ?? [];
    }

    /**
     * SQL文のキーを安全に引用符で囲むメソッドです。
     *
     * @param string $key
     * @return string
     */
    public static function quoteKey(string $key): string
    {
        $parts = explode('.', $key);
        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
                throw new InvalidArgumentException("Invalid SQL identifier part: {$part}");
            }
        }
        return implode('.', array_map([self::$connection, 'quoteIdentifier'], $parts));
    }

    /**
     * SQL文中のプレースホルダーとparamsのキーにprefixをつける
     *
     * @param string $sql 元のSQL文
     * @param array $params 元のパラメータ（`:name` → 値）
     * @param string $prefix プレフィックス（例: 'when_0_'）
     * @return array [$newSql, $newParams]
     */
    public static function aliasPlaceholders(string $sql, array $params, string $prefix): array
    {
        $newSql = $sql;
        $newParams = [];

        foreach ($params as $key => $value) {
            $oldPlaceholder = ':' . $key;
            $newKey = $prefix . $key;
            $newPlaceholder = ':' . $newKey;

            // SQL文中のプレースホルダーを書き換える
            $newSql = str_replace($oldPlaceholder, $newPlaceholder, $newSql);
            // パラメータもリネーム
            $newParams[$newKey] = $value;
        }
        return [$newSql, $newParams];
    }

    /**
     * プレースホルダ名を安全に生成する
    *
    * @param string $keyName 任意のキー名（例: 'user-id.1'）
    * @param string|null $suffix 一意性のための接尾語（例: '0'）
    * @return string
    */
    public static function safePlaceholder(string $keyName, ?string $suffix = null): string
    {
        static $counter = [];

        // プレースホルダ名に使えない文字をアンダースコアに変換
        $placeholder = preg_replace('/[^a-zA-Z0-9_]/', '_', $keyName);
        if ($placeholder === null) {
            throw new InvalidArgumentException("Invalid key name: {$keyName}");
        }
        // 先頭が数字なら先頭にアンダースコアを追加
        if (preg_match('/^\d/', $placeholder)) {
            $placeholder = '_' . $placeholder;
        }
        // カウンタ初期化
        if (!isset($counter[$placeholder])) {
            $counter[$placeholder] = 0;
        }
        if ($suffix === null) {
             // 複数回呼び出しに対応するためカウント付きハッシュ
            $suffix = $counter[$placeholder] . '_' . base_convert((string) crc32($keyName), 10, 36);
        }
        // カウンタを増やす（次の同じキー用に）
        $counter[$placeholder]++;

        return "{$placeholder}_{$suffix}";
    }

    /**
     * 第一引数の値が特定の クラス のオブジェクトのインスタンスであるかどうかを判定
     * @template T of object
     * @param mixed $obj
     * @param class-string<T> $className
     * @return bool
     * @phpstan-assert-if-true T $obj
     */
    public static function isClass($obj, $className)
    {
        return $obj instanceof $className;
    }

    /**
     * プレースホルダ付きSQLとparams配列から、デバッグ用のSQLを生成する
     *
     * @param array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * } $query
     */
    public static function dumpSQL(array $query): string
    {
        $sql = $query['sql'];
        $params = $query['params'];

        foreach ($params as $key => $value) {
            // 値をSQLリテラルに変換（null, string, int, bool）
            if (is_string($value)) {
                $repl = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $repl = 'NULL';
            } elseif (is_bool($value)) {
                $repl = $value ? '1' : '0';
            } else {
                $repl = $value;
            }
            // 名前付きプレースホルダを置換（:param → 値）
            // preg_replace でなく str_replace で OK（ユニークなキー前提）
            $sql = str_replace(':' . $key, $repl, $sql);
        }
        return (new SqlFormatter(new NullHighlighter()))->format($sql);
    }

    /**
     * SQL_Sequenceオブジェクトを生成する
     * @param string $seq シーケンス名 例: entry_id, blog_id, category_id, user_id
     * @param 'nextval'|'currval'|'setval'|'optimize' $method メソッド名 例: nextval, currval, setval, optimize
     * @param int|null $val
     * @return SQL_Sequence
     */
    public static function newSeq($seq, $method = 'nextval', $val = null)
    {
        $Obj = new SQL_Sequence();
        $Obj->setSequence($seq);
        $Obj->setMethod($method);
        $Obj->setValue($val);
        return $Obj;
    }

    /**
     * 指定されたsequence fieldのシーケンス番号を最適化する<br>
     * SQL::optimizeSeq('entry_id', dsn())<br>
     * UPDATE acms_sequence SET sequence_entry_id = ( LAST_INSERT_ID(sequence_entry_id + 1) )
     *
     * @static
     * @param string|SQL_Sequence $seq
     * @param Dsn|null $dsn
     * @param bool $plugin
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    public static function optimizeSeq($seq, $dsn = null, $plugin = false)
    {
        if (SQL::isClass($seq, 'SQL_Sequence')) {
            $Seq = $seq;
            $Seq->setMethod('optimize');
        } else {
            $Seq = SQL::newSeq($seq, 'optimize');
        }
        if ($plugin) {
            $Seq->setPluginFlag($plugin);
        }
        return $Seq->get($dsn);
    }

    /**
     * 指定されたsequence fieldのシーケンス番号を１進めてその値を返す<br>
     * SQL::nextval('entry_id', dsn())<br>
     * UPDATE acms_sequence SET sequence_entry_id = ( LAST_INSERT_ID(sequence_entry_id + 1) )
     *
     * @static
     * @param string|SQL_Sequence $seq
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    public static function nextval($seq, $dsn = null, $plugin = false)
    {
        if (SQL::isClass($seq, 'SQL_Sequence')) {
            $Seq    = $seq;
            $Seq->setMethod('nextval');
        } else {
            $Seq    = SQL::newSeq($seq, 'nextval');
        }
        if ($plugin) {
            $Seq->setPluginFlag($plugin);
        }
        return $Seq->get($dsn);
    }

    /**
     * 指定されたsequence fieldの現在のシーケンス番号を返す<br>
     * SQL::currval('entry_id', dsn())<br>
     * SELECT sequence_entry_id FROM acms_sequence
     *
     * @static
     * @param string|SQL_Sequence $seq
     * @param Dsn|null $dsn
     * @param bool $plugin
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    public static function currval($seq, $dsn = null, $plugin = false)
    {
        if (SQL::isClass($seq, 'SQL_Sequence')) {
            $Seq    = $seq;
            $Seq->setMethod('currval');
        } else {
            $Seq    = SQL::newSeq($seq, 'currval');
        }
        if ($plugin) {
            $Seq->setPluginFlag($plugin);
        }
        return $Seq->get($dsn);
    }

    /**
     * 指定されたsequence fieldを指定された値にセットする<br>
     * SQL::setval('entry_id', 10, dsn())<br>
     * UPDATE acms_sequence SET sequence_entry_id = 10
     *
     * @static
     * @param string|SQL_Sequence $seq
     * @param int $val
     * @param Dsn|null $dsn
     * @param bool $plugin
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    public static function setval($seq, $val, $dsn = null, $plugin = false)
    {
        if (SQL::isClass($seq, 'SQL_Sequence')) {
            $Seq    = $seq;
            $Seq->setMethod('setval');
            $Seq->setValue($val);
        } else {
            $Seq    = SQL::newSeq($seq, 'setval', $val);
        }
        if ($plugin) {
            $Seq->setPluginFlag($plugin);
        }
        return $Seq->get($dsn);
    }

    /**
     * SQL_Fieldオブジェクトを生成する<br>
     * SQL::newField('entry_title', 'entry')<br>
     * entry.entry_title
     *
     * @param SQL_Field|string|int $fd
     * @param string|null $scp
     * @param bool $quote
     * @return SQL_Field
     */
    public static function newField($fd, $scp = null, $quote = true)
    {
        $builder = new SQL_Field();
        $builder->setField($fd);
        $builder->setScope($scp);
        $builder->setQuote($quote);
        return $builder;
    }

    /**
     * 関数を生成するためのSQL_Field_Functionオブジェクトを生成する<br>
     * $funcに配列を指定すると、添字0を関数名、添字1以降を関数の引数として渡される<br>
     * SQL::newFunction('entry_title', ['SUBSTR', 0, 10])<br>
     * SUBSTR(entry_title, 0, 10)<br>
     *
     * $funcに文字列を指定すると、その文字列が関数名として渡される<br>
     * SQL::newFunction('entry_id', 'COUNT')<br>
     * COUNT(entry_id)
     *
     * @param SQL_Field|string|int|null $fd
     * @param array|string|null $func
     * @param string|null $scp
     * @param bool $quote
     * @return SQL_Field_Function
     */
    public static function newFunction($fd, $func = null, $scp = null, $quote = true)
    {
        $builder = new SQL_Field_Function();
        $builder->setField($fd);
        $builder->setFunction($func);
        $builder->setScope($scp);
        $builder->setQuote($quote);
        return $builder;
    }

    /**
     * SQLのGeometry関数を作成するためのSQL_Field_Functionオブジェクトを生成する
     * @param float|string $lat
     * @param float|string $lng
     * @param string|null $scp
     * @return SQL_Field_Function
     */
    public static function newGeometry($lat, $lng, $scp = null)
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric values.');
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        $point = sprintf('POINT(%F %F)', $lng, $lat);
        $fd = SQL::newField("'{$point}'", null, false);
        $builder = new SQL_Field_Function();
        $builder->setField($fd);
        $builder->setFunction('ST_GeomFromText');
        $builder->setScope($scp);
        return $builder;
    }

    /**
     * 演算子を生成するためのSQL_Field_Operatorオブジェクトを生成する<br>
     * SQL::newOpr('entry_id', 1, '>')<br>
     * entry_id > 1
     *
     * @param string|SQL_Field $fd
     * @param SQL_Field|string|int|float|null $val
     * @param string $opr
     * @param string|null $scp
     * @param array|string|null $func
     */
    public static function newOpr($fd, $val = null, $opr = '=', $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $builder = new SQL_Field_Operator();
            $builder->setField($fd);
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $builder = new SQL_Field_Operator();
            $builder->setField($fd);
            $builder->setFunction($func);
        } else {
            $builder = new SQL_Field_Operator();
            $builder->setField($fd);
            $builder->setScope($scp);
            $builder->setFunction($func);
        }
        $builder->setValue($val);
        $builder->setOperator($opr);

        return $builder;
    }

    /**
     * IN演算子を作成するためのSQL_Field_Operator_Inオブジェクトを生成する<br>
     * SQL::newOprIn('entry_id', [1, 2, 3, 4, 5])<br>
     * entry_id IN (1, 2, 3, 4, 5)
     *
     * @param string|SQL_Field $fd
     * @param array|SQL_Select $val
     * @param string|null $scp
     * @param array|string|null $func
     * @return SQL_Field_Operator_In
     */
    public static function newOprIn($fd, $val, $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
            $builder->setFunction($func);
        } else {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
            $builder->setScope($scp);
            $builder->setFunction($func);
        }
        $builder->setValue($val);

        return $builder;
    }

    /**
     * NOT IN演算子を作成するためのSQL_Field_Operator_Inオブジェクトを生成する<br>
     * SQL::newOprNotIn('entry_id', [1, 2, 3, 4, 5])<br>
     * entry_id NOT IN (1, 2, 3, 4, 5)
     *
     * @param string|SQL_Field $fd
     * @param array|SQL_Select $val
     * @param string|null $scp
     * @param array|string|null $func
     * @return SQL_Field_Operator_In
     */
    public static function newOprNotIn($fd, $val, $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
            $builder->setFunction($func);
        } else {
            $builder = new SQL_Field_Operator_In();
            $builder->setField($fd);
            $builder->setScope($scp);
            $builder->setFunction($func);
        }
        $builder->setValue($val);
        $builder->setNot(true);

        return $builder;
    }

    /**
     * EXISTS演算子を作成するためのSQL_Field_Operator_Existsオブジェクトを生成する<br>
     * SQL::newOprExists(SQL::newSelect('entry'))<br>
     * EXISTS (SELECT * FROM acms_entry)
     *
     * @param \SQL_Select $val
     * @param string|null $scp
     * @return SQL_Field_Operator_Exists
     */
    public static function newOprExists($val, $scp = null)
    {
        $builder = new SQL_Field_Operator_Exists();
        $builder->setScope($scp);
        $builder->setValue($val);

        return $builder;
    }

    /**
     * NOT EXISTS演算子を作成するためのSQL_Field_Operator_Existsオブジェクトを生成する <br>
     * SQL::newOprExists(SQL::newSelect('entry'))<br>
     * NOT EXISTS (SELECT * FROM acms_entry)
     *
     * @param \SQL_Select $val
     * @param string|null $scp
     * @return SQL_Field_Operator_Exists
     */
    public static function newOprNotExists($val, $scp = null)
    {
        $builder = new SQL_Field_Operator_Exists();
        $builder->setValue($val);
        $builder->setNot(true);

        return $builder;
    }

    /**
     * BETWEEN演算子を作成するためのSQL_Field_Operator_Betweenオブジェクトを生成する<br>
     * SQL::newOprBw('entry_id', 1, 10)<br>
     * entry_id BETWEEN 1 AND 10
     *
     * @template T of string|int|float|null
     * @param string|SQL_Field $fd
     * @param T $a 文字列（日付型の文字列）または数値
     * @param T $b 文字列（日付型の文字列）または数値
     * @param string|null $scp
     * @param array|string|null $func
     * @return SQL_Field_Operator_Between<T>
     */
    public static function newOprBw($fd, $a, $b, $scp = null, $func = null)
    {
        if (SQL::isClass($fd, 'SQL_Field_Function')) {
            $builder = new SQL_Field_Operator_Between();
            $builder->setField($fd);
        } elseif (SQL::isClass($fd, 'SQL_Field')) {
            $builder = new SQL_Field_Operator_Between();
            $builder->setField($fd);
            $builder->setFunction($func);
        } else {
            $builder = new SQL_Field_Operator_Between();
            $builder->setField($fd);
            $builder->setScope($scp);
            $builder->setFunction($func);
        }
        $builder->setBetween($a, $b);

        /** @var SQL_Field_Operator_Between<T> $builder */
        return $builder;
    }

    /**
     * CASE文を作成するためのSQL_Field_Caseオブジェクトを生成する <br />
     * $case = SQL::newCase();<br>
     * $case->add(SQL::newOpr('entry_status', 'draft' '='), '下書き');<br>
     * $case->add(SQL::newOpr('entry_status', 'open' '='), '公開');<br>
     * $case->add(SQL::newOpr('entry_status', 'close' '='), '非公開');<br>
     * $case->setElse('下書き');<br>
     * CASE<br>
     *   WHEN entry_status = 'draft' THEN '下書き'<br>
     *   WHEN entry_status = 'open' THEN '公開'<br>
     *   WHEN entry_status = 'close' THEN '非公開'<br>
     *   ELSE '下書き'
     *
     * @param SQL|string|null $simple 単純CASE文を作成する場合はSQLオブジェクトまたは文字列を指定する
     * @return SQL_Field_Case
     */
    public static function newCase($simple = null)
    {
        $builder = new SQL_Field_Case();
        $builder->setSimple($simple);
        return $builder;
    }

    /**
     * WHERE句を生成するためのSQL_Whereオブジェクトを生成する
     * @return SQL_Where
     */
    public static function newWhere()
    {
        $builder = new SQL_Where();
        return $builder;
    }

    /**
     * TABLEを指定してSELECT句を生成する為のSQL_Selectを返す
     *
     * @static
     * @param \SQL_Select|\SQL_Field|string|null $tb
     * @param string|null $als
     * @param bool $straight_join
     * @return SQL_Select
     */
    public static function newSelect($tb = null, $als = null, $straight_join = false)
    {
        $builder = new SQL_Select();
        if ($tb) {
            $builder->setTable($tb, $als, $straight_join);
        }
        return $builder;
    }

    /**
     * TABLEを指定してINSERT句を生成する為のSQL_Insertを返す
     *
     * @static
     * @param string|null $tb
     * @return SQL_Insert
     */
    public static function newInsert($tb = null)
    {
        $builder = new SQL_Insert();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * TABLEを指定してINSERT句（バルク）を生成する為のSQL_BulkInsertを返す
     *
     * @static
     * @param string|null $tb
     * @return SQL_BulkInsert
     */
    public static function newBulkInsert($tb = null)
    {
        $builder = new SQL_BulkInsert();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * TABLEを指定してREPLACE句を生成する為のSQL_Replaceを返す
     *
     * @static
     * @param string|null $tb
     * @return SQL_Replace
     */
    public static function newReplace($tb = null)
    {
        $builder = new SQL_Replace();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * TABLEを指定してUPDATE句を生成する為のSQL_Updateを返す
     *
     * @static
     * @param string|null $tb
     * @return SQL_Update
     */
    public static function newUpdate($tb = null)
    {
        $builder = new SQL_Update();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * TABLEを指定してINSERT ON DUPLICATE KEY UPDATE句を生成する為のSQL_InsertOrUpdateを返す
     *
     * @static
     * @param string|null $tb
     * @param string|null $als
     * @return SQL_InsertOrUpdate
     */
    public static function newInsertOrUpdate($tb = null, $als = null)
    {
        $builder = new SQL_InsertOrUpdate();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * TABLEを指定してDELETE句を生成する為のSQL_Deleteを返す
     *
     * @static
     * @param string|null $tb
     * @return SQL_Delete
     */
    public static function newDelete($tb = null)
    {
        $builder = new SQL_Delete();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }

    /**
     * Where句を指定してDelete句を生成する為のSQL文を返す
     *
     * @deprecated 未使用のため非推奨
     * @param string|null $tb
     * @param \SQL_Where|null $w
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    public static function delete($tb, $w = null, $dsn = null)
    {
        $builder = SQL::newDelete($tb);
        if ($w) {
            $builder->setWhere($w);
        }
        return $builder->get($dsn);
    }

    /**
     * TABLEを指定してSHOW TABLE句を生成する為のSQL_ShowTableを返す
     * @param string|null $tb
     * @return SQL_ShowTable
     */
    public static function showTable($tb = null)
    {
        $builder = new SQL_ShowTable();
        if ($tb) {
            $builder->setTable($tb);
        }
        return $builder;
    }
}
