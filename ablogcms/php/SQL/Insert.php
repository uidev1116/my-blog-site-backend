<?php

/**
 * SQL_Insert
 *
 * SQLヘルパのInsertメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Insert extends SQL
{
    /**
     * @var array<string, \SQL_Field_Function|string|int|float|null>|\SQL_Select|null
     */
    public $_insert = null;

    /**
     * @var string|null
     */
    public $_table = null;

    /**
     * 指定されたfieldにINSERT句を生成する。<br>
     * $SQL->addInsert('entry_code', 'abc');<br>
     * INSERT INTO acms_entry (entry_code) VALUES ('abc')
     *
     * @param string $fd
     * @param \SQL_Field_Function|string|int|float|null $val
     * @return bool
     */
    public function addInsert(string $fd, $val)
    {
        $this->_insert[$fd] = $val;
        return true;
    }

    /**
     * @param string $tb
     * @return void
     */
    public function setTable(string $tb)
    {
        $this->_table = $tb;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table name is not set for SQL_Insert');
        }
        if (!$this->_insert) {
            throw new InvalidArgumentException('Insert values are not set for SQL_Insert');
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
                    $data = array_merge($data, $val->getParams());
                    $qb->setValue(self::quoteKey($fd), $val->getSQL($dsn));
                } else {
                    $data[$placeholder] = $val;
                    $qb->setValue(self::quoteKey($fd), ":{$placeholder}");
                }
            }
            $qb->setParameters($data);
        } else {
            throw new InvalidArgumentException('Insert values are not set for SQL_Insert');
        }
        return [
            'sql' => $qb->getSQL(),
            'params' => $qb->getParameters(),
        ];
    }
}
