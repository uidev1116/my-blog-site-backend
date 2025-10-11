<?php

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * SQL_Trait_Limit
 *
 * SQLのLIMIT句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Limit
{
    /**
     * @var array{
     *   limit: int,
     *   offset: int
     * }|null
     */
    public $_limit = null;

    /**
     * 指定された数のレコードを返す<br>
     * $SQL->setLimit(30, 10);<br>
     * LIMIT 10, 30
     *
     * @param int $lmt
     * @param int $off
     * @return bool
     */
    public function setLimit($lmt, $off = 0)
    {
        $this->_limit = [
            'limit' => intval($lmt),
            'offset' => intval($off),
        ];
        return true;
    }

    /**
     * Limit句を生成する
     *
     * @param QueryBuilder $qb
     * @return void
     */
    protected function limit(QueryBuilder $qb): void
    {
        if (is_null($this->_limit)) {
            return;
        }
        $qb->setMaxResults($this->_limit['limit']);
        if ($this->_limit['offset'] > 0) {
            $qb->setFirstResult($this->_limit['offset']);
        }
    }
}
