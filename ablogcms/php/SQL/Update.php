<?php

/**
 * SQL_Update
 *
 * SQLヘルパのUpdateメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Update extends SQL_Where
{
    /**
     * @var array<string, \SQL|string|int|float|null>|null
     */
    public $_update = [];

    /**
     * @var string|null
     */
    public $_table = null;

    /**
     * 指定されたfieldにUPDATE句を生成する。<br>
     * $SQL->addUpdate('entry_code', 'abc');<br>
     * UPDATE acms_entry SET entry_code = 'abc'
     *
     * @param string $fd
     * @param \SQL|string|int|float|null $val
     * @return bool
     */
    public function addUpdate($fd, $val)
    {
        $this->_update[$fd] = $val;
        return true;
    }

    /**
     * @param string|null $fd
     * @param \SQL|string|int|float|null $val
     * @return true
     */
    public function setUpdate($fd = null, $val = null)
    {
        $this->_update = [];
        if ($fd) {
            $this->addUpdate($fd, $val);
        }
        return true;
    }

    /**
     * @param string $tb
     * @return void
     */
    public function setTable($tb)
    {
        $this->_table = $tb;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null)
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table name is not set for SQL_Update');
        }
        if (!$this->_update) {
            throw new InvalidArgumentException('Update values are not set for SQL_Update');
        }
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $table = self::quoteKey($tablePrefix . $this->_table);
        $qb = self::$connection->createQueryBuilder();

        $qb->update($table);

        $data = [];
        foreach ($this->_update as $fd => $val) {
            $placeholder = self::safePlaceholder($fd);
            if (self::isClass($val, 'SQL')) {
                $updateValue = $val->getSQL($dsn);
                $data = array_merge($data, $val->getParams());
                $qb->set(self::quoteKey($fd), $updateValue);
            } else {
                $qb->set(self::quoteKey($fd), ":{$placeholder}");
                $data[$placeholder] = $val;
            }
        }
        $qb->setParameters($data);

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
