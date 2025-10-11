<?php

/**
 * SQL_BulkInsert
 *
 * SQLヘルパのBulkInsertメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_BulkInsert extends SQL
{
    /**
     * @var list<array<string, scalar|null>> $_insert
     */
    public $_insert = [];

    /**
     * @var string[]
     */
    public $_columns = [];

    /**
     * @var string|null
     */
    public $_table = null;

    /**
     * 指定されたカラムをINSERT句にセットする
     *
     * @param string[] $columns
     * @return boolean
     */
    public function setColumns(array $columns): bool
    {
        $this->_columns = $columns;
        return true;
    }

    /**
     * 指定されたカラムをINSERT句に追加する
     *
     * @param string $column
     * @return boolean
     */
    public function addColumn(string $column): bool
    {
        $this->_columns[] = $column;
        return true;
    }

    /**
     * 指定されたデータをINSERT句に追加する
     *
     * @param array<string, scalar|null> $data
     * @return bool
     */
    public function addInsert(array $data): bool
    {
        $this->_insert[] = $data;
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
     * データがセットされているかどうかを確認します
     *
     * @return boolean
     */
    public function hasData(): bool
    {
        return !!$this->_insert;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        if (!$this->_table) {
            throw new InvalidArgumentException('Table name is not set for SQL_BulkInsert');
        }
        if (!$this->_insert) {
            throw new InvalidArgumentException('Insert values are not set for SQL_BulkInsert');
        }
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $table = self::quoteKey($tablePrefix . $this->_table);

        if (!$this->_columns) {
            $this->_columns = array_keys(reset($this->_insert)); // カラムが未セットなら、データから自動的に取得
        }
        if ($this->_columns && $this->_insert) {
            $safeColumns = implode(', ', array_map([self::class, 'quoteKey'], $this->_columns));
            $sql = "INSERT INTO {$table} ({$safeColumns}) VALUES ";
            $params = [];
            $placeholders = [];

            foreach ($this->_insert as $row) {
                $rowPlaceholder = [];
                foreach ($this->_columns as $i => $column) {
                    $placeholder = self::safePlaceholder("{$column}_{$i}");
                    $rowPlaceholder[] = ":{$placeholder}";
                    $params[$placeholder] = $row[$column] ?? null;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholder) . ')';
            }
            $sql .= implode(", ", $placeholders);

            return [
                'sql' => $sql,
                'params' => $params,
            ];
        }
        throw new InvalidArgumentException('Insert values are not set for SQL_BulkInsert');
    }
}
