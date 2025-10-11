<?php

/**
 * SQL_ShowTable
 *
 * SQLヘルパのSHOW TABLEメソッド群です。
 *
 * @package php
 */
class SQL_ShowTable extends SQL
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
        $this->_table = $tb;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';

        if (!$this->_table) {
            return [
                'sql' => 'SHOW TABLES',
                'params' => [],
            ];
        }
        return [
            'sql' => 'SHOW TABLES LIKE :table',
            'params' => ['table' => $tablePrefix . $this->_table],
        ];
    }
}
