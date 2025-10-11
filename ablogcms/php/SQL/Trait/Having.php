<?php

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * SQL_Trait_Having
 *
 * SQLのHAVING句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Having
{
    /**
     * @var array{
     *  having: SQL|string,
     *  glue: 'AND'|'OR'
     * }[]
     */
    public $_havings = [];

    /**
     * 指定された条件式でHAVING句を生成する<br>
     * $SQL->addHaving('entry_id > 5', 'AND');<br>
     * HAVING ( 1 AND entry_id > 5 )
     *
     * @param SQL|string $h
     * @param 'AND'|'OR' $gl
     * @return true
     */
    public function addHaving($h, $gl = 'AND')
    {
        $this->_havings[]   = [
            'having'    => $h,
            'glue'      => $gl,
        ];
        return true;
    }

    /**
     * @param SQL|string|null $h
     * @param 'AND'|'OR' $gl
     * @return true
     */
    public function setHaving($h = null, $gl = 'AND')
    {
        $this->_havings = [];
        if ($h) {
            $this->addHaving($h, $gl);
        }
        return true;
    }

    /**
     * Having句を生成する
     *
     * @param QueryBuilder $qb
     * @param Dsn|null $dsn
     * @return array<string, mixed>
     */
    protected function having(QueryBuilder $qb, ?array $dsn = null): array
    {
        if (count($this->_havings) === 0) {
            return [];
        }
        $first = true;
        $params = [];
        foreach ($this->_havings as $having) {
            $sql = $having['having'];
            $havingSQL = null;
            if (self::isClass($sql, 'SQL')) {
                $havingSQL = $sql->getSQL($dsn);
                $params = array_merge($params, $sql->getParams($dsn));
            }
            if ($havingSQL === null) {
                continue;
            }
            if ($first) {
                $qb->having($havingSQL);
                $first = false;
            } else {
                $qb->andHaving($havingSQL);
            }
        }
        return $params;
    }
}
