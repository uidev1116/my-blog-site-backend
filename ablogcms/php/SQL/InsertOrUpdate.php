<?php

/**
 * SQL_InsertOrUpdate
 *
 * SQLヘルパの INSERT ON DUPLICATE KEY UPDATE メソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_InsertOrUpdate extends SQL_Insert
{
    /**
     * @var array<string, SQL_Field_Function|string|int|float|null>|\SQL_Select|null
     */
    public $_insert    = null;

    /**
     * @var array<string, \SQL|string|int|float|null>|null
     */
    public $_update    = null;

    /**
     * @var string|null
     */
    public $_table     = null;

    /**
     * 指定されたfieldにON DUPLICATE KEY UPDATE句を生成する。<br>
     * $SQL->addUpdate('entry_code', 'abc');<br>
     * ... ON DUPLICATE KEY UPDATE entry_code = 'abc'
     *
     * @param string $fd
     * @param \SQL|string|int|float|null $val
     * @return bool
     */
    public function addUpdate(string $fd, $val)
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
        $this->_update  = [];
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
        $this->_table   = $tb;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table name is not set for SQL_InsertOrUpdate');
        }
        if (!$this->_insert) {
            throw new InvalidArgumentException('Insert values are not set for SQL_InsertOrUpdate');
        }

        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $table = self::quoteKey($tablePrefix . $this->_table);

        if (is_array($this->_insert)) {
            $qb = self::$connection->createQueryBuilder();
            $qb->insert($table);
            $data = [];
            foreach ($this->_insert as $fd => $val) {
                $placeholder = self::safePlaceholder($fd);
                if (self::isClass($val, 'SQL_Field_Function')) {
                    $insertValue = $val->getSQL($dsn);
                    $data = array_merge($data, $val->getParams());
                } else {
                    $insertValue = $val;
                }
                $data[$placeholder] = $insertValue;
                $qb->setValue(self::quoteKey($fd), ":{$placeholder}");
            }
            $qb->setParameters($data);

            $sql = $qb->getSQL() . ' ON DUPLICATE KEY UPDATE ';
            $updateSQL = array_map(
                fn($fd) => sprintf('%s = VALUES(%s)', self::quoteKey($fd), self::quoteKey($fd)),
                array_keys($this->_update ?? [])
            );
            $sql = $sql . implode(', ', $updateSQL);
        } else {
            throw new InvalidArgumentException('Insert values must be an array for SQL_InsertOrUpdate');
        }

        return [
            'sql' => $sql,
            'params' => $qb->getParameters(),
        ];
    }
}
