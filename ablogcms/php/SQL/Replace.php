<?php

/**
 * SQL_Replace
 *
 * SQLヘルパのReplaceメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Replace extends SQL
{
    /**
     * @var array<string, string|int|float|null>|\SQL_Select|null
     */
    public $_replace = null;

    /**
     * @var string|null
     */
    public $_table = null;

    /**
     * 指定されたfieldにREPLACE句を生成する。<br>
     * $SQL->addRepace('entry_code', 'abc');<br>
     * REPLACE INTO acms_entry (entry_code) VALUES ('abc')
     *
     * @param string $fd
     * @param string|int|float|null $val
     * @return bool
     */
    public function addReplace(string $fd, $val)
    {
        $this->_replace[$fd] = $val;
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
    public function get($dsn = null): array
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table name is not set for SQL_Replace');
        }
        if (!$this->_replace) {
            throw new InvalidArgumentException('Replace values are not set for SQL_Replace');
        }
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $table = self::quoteKey($tablePrefix . $this->_table);

        $sql = "REPLACE INTO {$table}";
        $params = [];
        if (self::isClass($this->_replace, 'SQL_Select')) {
            $sql .= " {$this->_replace->getSQL($dsn)}";
            $params = $this->_replace->getParams();
        } elseif (is_array($this->_replace)) {
            $fds = [];
            $vals = [];
            foreach ($this->_replace as $fd => $val) {
                $placeholder = self::safePlaceholder($fd);
                $fds[] = self::quoteKey($fd);
                $vals[] = ":{$placeholder}";
                $params[$placeholder] = $val;
            }
            $sql .= "\n(" . implode(', ', $fds) . ')';
            $sql .= "\n VALUES (" . implode(', ', $vals) . ')';
        } else {
            throw new InvalidArgumentException('Replace values must be an array or SQL_Select instance');
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }
}
