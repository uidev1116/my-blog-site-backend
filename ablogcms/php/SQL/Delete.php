<?php

/**
 * SQL_Delete
 *
 * SQLヘルパのDeleteメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Delete extends SQL_Where
{
    /**
     * @var string|null
     */
    public $_table  = null;

    /**
     * @param string $tb
     */
    public function setTable($tb)
    {
        $this->_table   = $tb;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null)
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table not set for SQL_Delete');
        }
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $table = self::quoteKey($tablePrefix . $this->_table);
        $qb = self::$connection->createQueryBuilder();

        $qb->delete($table);

        // where
        if (count($this->_wheres) > 0) {
            $where = $this->where($dsn);
            $whereSql = $where['sql'];
            if ($whereSql) {
                $whereParams = $where['params'];
                $qb->where($whereSql);
                foreach ($whereParams as $key => $value) {
                    $qb->setParameter($key, $value);
                }
            }
        }
        return [
            'sql' => $qb->getSQL(),
            'params' => $qb->getParameters(),
        ];
    }
}
