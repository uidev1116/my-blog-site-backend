<?php

use Doctrine\DBAL\Query\QueryBuilder;
use Acms\Services\Facades\Database;

/**
 * SQL_Trait_Order
 *
 * SQLのORDER BY句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Order
{
    /**
     * @var array{
     *   order: 'ASC'|'DESC',
     *   field: \SQL_Field
     * }[]
     */
    public $_orders = [];

    /**
     * @var array{
     *   fd: \SQL_Field,
     *   values: array
     * }|null
     */
    public $_fdOrders = null;

    /**
     * 指定されたfieldでORDER BY句を生成する<br>
     * $SQL->addOrder('entry_id', 'ASC', 'acms_entry');<br>
     * ORDER BY acms_entry.entry_id ASC
     *
     * @param \SQL_Field|string $fd
     * @param 'ASC'|'DESC'|'asc'|'desc' $ord
     * @param string|null $scp
     * @return true
     */
    public function addOrder($fd, $ord = 'ASC', $scp = null)
    {
        $this->_orders[] = [
            'order' => (strtoupper($ord) === 'ASC') ? 'ASC' : 'DESC',
            'field' => self::isClass($fd, 'SQL_Field') ? $fd : self::newField($fd, $scp),
        ];
        return true;
    }

    /**
     * 指定されたorderのSQLを生成する<br>
     * $SQL->setOrder('entry_id', 'ASC', 'acms_entry');<br>
     * LIMIT 10, 30
     *
     * @param \SQL_Field|string|null $fd
     * @param 'ASC'|'DESC'|'asc'|'desc' $ord
     * @param string|null $scp
     * @return true
     */
    public function setOrder($fd = null, $ord = 'ASC', $scp = null)
    {
        $this->_orders = [];
        if ($fd) {
            $this->addOrder($fd, $ord, $scp);
        }
        return true;
    }

    /**
     * @param \SQL_Field|string|null $fd
     * @param array $values
     * @param string|null $scp
     * @return void
     */
    public function setFieldOrder($fd = null, $values = [], $scp = null)
    {
        if ($fd === null) {
            throw new InvalidArgumentException('Field name is required for setFieldOrder');
        }
        $this->_fdOrders = [
            'fd' => self::isClass($fd, 'SQL_Field') ? $fd : self::newField($fd, $scp),
            'values' => $values,
        ];
    }

    /**
     * Order句を生成する
     *
     * @param QueryBuilder $qb
     * @param Dsn|null $dsn
     * @return void
     */
    protected function order(QueryBuilder $qb, ?array $dsn = null): void
    {
        if ($this->_orders) {
            $first = true;
            foreach ($this->_orders as $order) {
                $field = $order['field'];
                $ord = strtoupper($order['order']);
                if (!in_array($ord, ['ASC', 'DESC'], true)) {
                    throw new InvalidArgumentException("Invalid order direction: $ord");
                }
                if ($first) {
                    $qb->orderBy($field->getSQL($dsn), $ord);
                    $first = false;
                } else {
                    $qb->addOrderBy($field->getSQL($dsn), $ord);
                }
            }
        } elseif (!is_null($this->_fdOrders)) {
            $quotedValues = array_map(function ($val) {
                return is_numeric($val) ? $val : Database::quote($val); // エスケープ処理
            }, $this->_fdOrders['values']);
            $field = $this->_fdOrders['fd']->getSQL($dsn);
            $values = implode(', ', $quotedValues);
            $qb->orderBy("FIELD({$field}, {$values})");
        }
    }
}
