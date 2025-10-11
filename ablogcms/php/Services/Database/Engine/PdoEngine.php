<?php

namespace Acms\Services\Database\Engine;

use Acms\Services\Facades\Logger as AcmsLogger;
use App;
use SQL;
use PDO;
use PDOStatement;
use PDOException;

/**
 * Class Pdo
 * @package Acms\Services\Database\Engine
 */
class PdoEngine extends Base
{
    /**
     * @var \PDOStatement|null
     */
    protected $statement;

    /**
     * @var bool
     */
    protected $throwException = false;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * connect mysql server
     *
     * @param array $dsn
     */
    public function connect($dsn)
    {
        $connect_str = 'mysql:host=';
        $host = explode(':', $dsn['host']);
        $connect_str .= $host[0] . ';';
        if (!empty($dsn['name'])) {
            $connect_str .= 'dbname=' . $dsn['name'] . ';';
        }
        if (!empty($dsn['port']) || isset($host[1])) {
            $port = empty($dsn['port']) ? $host[1] : $dsn['port'];
            $connect_str .= 'port=' . $port . ';';
        }

        $options = [];

        $connect_str .= 'charset=' . $this->getCharset($dsn);

        try {
            $this->connection = new PDO(
                $connect_str,
                $dsn['user'],
                $dsn['pass'],
                $options
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw $e;
        }

        $charset = isset($dsn['charset']) ? $dsn['charset'] : 'UTF-8';
        $this->debug = !empty($dsn['debug']);
        $this->isBenchmarkMode = isBenchMarkMode();
        $this->dsn = [
            'type' => isset($dsn['type']) ? $dsn['type'] : null,
            'debug' => $this->debug,
            'charset' => $charset,
        ];

        $q = "SET SESSION sql_mode='ALLOW_INVALID_DATES'";
        $this->query(['sql' => $q, 'params' => []], 'exec');
    }

    /**
     * reconnect mysql server
     *
     * @param $dsn
     * @return void
     */
    public function reconnect($dsn)
    {
        $this->connection = null;
        $this->statement = null;
        $this->connect($dsn);
    }

    /**
     * データベースサーバーへの接続チェック
     *
     * @return bool
     */
    public function checkConnection($dsn)
    {
        try {
            $dsn['name'] = '';
            $this->connect($dsn);
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    /**
     * データベースへの接続チェック
     *
     * @return bool
     */
    public function checkConnectDatabase($dsn)
    {
        if (empty($dsn['name'])) {
            return false;
        }
        try {
            $this->connect($dsn);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * 例外をスローするか設定
     *
     * @param bool $throw
     */
    public function setThrowException($throw = true)
    {
        $this->throwException = $throw;
    }

    /**
     * 例外をスローするかの設定を取得
     * @return bool
     */
    public function getThrowException()
    {
        return $this->throwException;
    }

    /**
     * クエリ用の文字列をクオートする
     *
     * @param string $string
     * @return string
     */
    public static function quote($string)
    {
        $DB = self::singleton(dsn());
        return $DB->connection->quote($string);
    }

    /**
     * Get SQL Server Version
     *
     * @return string
     */
    public function getVersion()
    {
        static $version = false;
        if ($version) {
            return $version;
        }
        $db = self::singleton(dsn());
        $version = (string) $db->query([
            'sql' => 'select version()',
            'params' => []
        ], 'one');
        return $version;
    }

    /**
     * SQL文を指定してmodeに応じたDB操作結果を返す<br>
     * 'row'    => 最初の行の連想配列を返す(array)<br>
     * 'all'    => すべての行を連想配列で返す(array)<br>
     * 'exec'   => mysql_query()の結果を返す(resource)<br>
     * 'fetch'  => fetchキャッシュを生成する(bool)<br>
     * 'one'    => 最初の行の最初のfieldを返す<br>
     * 'seq'    => insert,update,deleteされた件数を返す(int)
     *
     * @param array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param string $mode
     * @param boolean $buffered
     * @param boolean $auditLog
     * @return array|bool|resource|int|string
     *
     * @throws \ErrorException
     */
    public function query($sql, $mode = 'row', $buffered = true, $auditLog = true)
    {
        global $query_result_count;
        $query_result_count++;

        try {
            if (!isset($sql['sql'])) { // @phpstan-ignore-line
                throw new \InvalidArgumentException('Invalid SQL format');
            }
            $this->hook($sql);
            $sqlString = $sql['sql'];
            $sqlParams = $sql['params'];
            $this->params = $sqlParams;

            $start_time = microtime(true);
            if ($buffered === false) {
                $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            } else {
                $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
            $stmt = $this->connection->prepare($sqlString);
            $stmt->execute($sqlParams);

            $exe_time = sprintf('%0.6f', microtime(true) - $start_time);
            $this->saveProcessingTime($sql, $exe_time);
            $this->affectedRows = $stmt ? $stmt->rowCount() : 0;
            $this->columnCount = $stmt ? $stmt->columnCount() : 0;
            $this->statement = $stmt;

            $method = strtolower($mode) . 'Mode';
            if (method_exists($this, $method)) {
                $result = $this->{$method}($sql, $stmt); // @phpstan-ignore-line
            } else {
                $result = $this->etcMode($sql, $stmt);
            }
            return $result;
        } catch (PDOException $e) {
            if ($auditLog) {
                AcmsLogger::debug($e->getMessage(), [
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'sql' => SQL::dumpSQL($sql),
                ]);
            }
            if ($this->debug) {
                $code = intval($e->getCode());
                $exception = new \ErrorException($e->getMessage(), $code, E_USER_WARNING, $e->getFile(), $e->getLine(), App::getExceptionStack());
                if ($this->throwException) {
                    throw $exception;
                } else {
                    App::setExceptionStack($exception);
                }
            }
            if ($mode === 'all') {
                return [];
            } else {
                return false;
            }
        }
    }

    /**
     * プレースホルダー付きのSQL文字列を取得
     *
     * @return string
     */
    public function getQueryString(): string
    {
        if ($this->statement === null) {
            return '';
        }
        return $this->statement->queryString;
    }

    /**
     * プレースホルダーの値を取得
     *
     * @return array
     */
    public function getQueryParameter(): array
    {
        return $this->params;
    }

    /**
     * sql文を指定して1行ずつfetchされた値を返す
     * $DB->query($SQL->get(dsn()), 'fetch');<br>
     * while ( $row = $DB->fetch($q) ) {<br>
     *     $Config->addField($row['config_key'], $row['config_value']);<br>
     * }
     *
     * @deprecated パフォーマンスの問題で非推奨
     * @param array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * } | null $sql
     * @param bool $reset
     * @return array|bool
     */
    public function fetch($sql = null, $reset = false)
    {
        if ($sql === null) {
            return false;
        }
        $this->hook($sql);
        $encodedSql = json_encode($sql, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedSql === false) {
            throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        $id = hash('sha256', $encodedSql, false);
        if (!isset($this->fetch[$id])) {
            return false;
        }
        if ($reset) {
            $this->fetch[$id]->closeCursor();
            unset($this->fetch[$id]);
            return false;
        }
        $row = $this->fetch[$id]->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            $this->fetch[$id]->closeCursor();
            unset($this->fetch[$id]);
            return false;
        }
        return $row;
    }

    /**
     * リソースを指定して1行ずつfetchされた値を返す
     * $statement = Database::query($sql->get(dsn()), 'exec');
     * while ($row = $DB->next($statement)) {
     *     $Config->addField($row['config_key'], $row['config_value']);
     * }
     *
     * @param PDOStatement|false $statement
     * @return array|false
     */
    public function next(PDOStatement|false $statement)
    {
        if (!$statement) {
            return false;
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $statement->closeCursor();
            return false;
        }
        return $row;
    }

    /**
     * query()の結果を返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return mixed
     */
    protected function execMode($sql, $response)
    {
        return $response;
    }

    /**
     * insert,update,deleteされた件数を返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return int
     */
    protected function seqMode($sql, $response)
    {
        if (is_bool($response)) {
            return $this->connection->lastInsertId();
        } else {
            $one = $this->query([
                'sql' => 'select last_insert_id()',
                'params' => [],
            ], 'one');
            $response->closeCursor();

            return intval($one);
        }
    }

    /**
     * すべての行を連想配列で返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return array
     */
    protected function allMode($sql, $response)
    {
        $all = $response->fetchAll(PDO::FETCH_ASSOC);
        $response->closeCursor();

        return $all;
    }

    /**
     * 最初の行を配列で返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return array
     */
    protected function listMode($sql, $response)
    {
        $list = [];
        while ($row = $response->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = array_shift($row);
        }
        $response->closeCursor();

        return $list;
    }

    /**
     * 最初の行の最初のcolumnの値を返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return string
     */
    protected function oneMode($sql, $response)
    {
        if (!$row = $response->fetch(\PDO::FETCH_ASSOC)) {
            return '';
        }
        $one = array_shift($row);
        $response->closeCursor();

        return $one;
    }

    /**
     * 最初の行の連想配列を返す
     *
     * @param array{
     *   sql: string,
     *   params: list<mixed>|array<string, mixed>,
     * } $sql
     * @param mixed $response
     * @return array
     */
    protected function rowMode($sql, $response)
    {
        $row = $response->fetch(\PDO::FETCH_ASSOC);
        $response->closeCursor();

        return $row;
    }

    /**
     * Returns metadata for a column in a result set
     *
     * @param int $column
     *
     * @return array
     */
    public function columnMeta($column)
    {
        return $this->statement->getColumnMeta($column);
    }
}
