<?php

/**
 * SQL_Trait_Join
 *
 * SQLのJOIN句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Join
{
    /**
     * @var array<array{
     *  table: \SQL_Select|string,
     *  a: \SQL_Field|string,
     *  b: \SQL_Field|string,
     *  where: \SQL_Where|null
     * }>
     */
    public $_leftJoins = [];

    /**
     * @var array<array{
     *  table: \SQL_Select|string,
     *  a: string|\SQL_Field,
     *  b: string|\SQL_Field,
     *  als?: string|null,
     *  scp?: string|null,
     *  where: \SQL_Where|null
     * }>
     */
    public $_innerJoins = [];

    /**
     * @var bool
     */
    public $_straightJoin = false;

    /**
     * 指定されたtableと条件からtableを結合する。<br>
     * $SQL->addLeftJoin('category', 'category_id', 'entry_category_id', 'category', 'entry');<br>
     * LEFT JOIN acms_category AS category ON category.category_id = entry.entry_category_id
     *
     * @param \SQL_Select|string $tb
     * @param \SQL_Field|string $a
     * @param \SQL_Field|string $b
     * @param string|null $aScp
     * @param string|null $bScp
     * @param \SQL_Where|null $where
     * @return true
     */
    public function addLeftJoin($tb, $a, $b, $aScp = null, $bScp = null, $where = null)
    {
        $A = self::isClass($a, 'SQL_Field') ? $a : self::newField($a, $aScp);
        $B = self::isClass($b, 'SQL_Field') ? $b : self::newField($b, $bScp);
        $this->_leftJoins[] = [
            'table' => $tb,
            'a' => $A,
            'b' => $B,
            'where' => $where,
        ];
        return true;
    }

    /**
     * @param \SQL_Select|string|null $tb
     * @param \SQL_Field|string|null $a
     * @param \SQL_Field|string|null $b
     * @param string|null $aScp
     * @param string|null $bScp
     * @param \SQL_Where|null $where
     * @return true
     */
    public function setLeftJoin($tb = null, $a = null, $b = null, $aScp = null, $bScp = null, $where = null)
    {
        $this->_leftJoins = [];
        if ($tb && $a && $b) {
            $this->addLeftJoin($tb, $a, $b, $aScp, $bScp, $where);
        }
        return true;
    }

    /**
     * 指定されたtableと条件からINNER JOIN句を生成する。<br>
     * $SQL->addInnerJoin('category', 'category_id', 'entry_category_id', 'category', 'acms_entry');<br>
     * INNER JOIN acms_category AS category ON category.category_id = entry.entry_category_id
     *
     * @param \SQL_Select|string $tb
     * @param \SQL_Field|string $a
     * @param \SQL_Field|string $b
     * @param string|null $aScp
     * @param string|null $bScp
     * @param \SQL_Where|null $where
     * @return true
     */
    public function addInnerJoin($tb, $a, $b, $aScp = null, $bScp = null, $where = null)
    {
        $A = self::isClass($a, 'SQL_Field') ? $a : self::newField($a, $aScp);
        $B = self::isClass($b, 'SQL_Field') ? $b : self::newField($b, $bScp);
        $this->_innerJoins[] = [
            'table' => $tb,
            'a' => $A,
            'b' => $B,
            'where' => $where,
        ];
        return true;
    }

    /**
     * @param \SQL_Select|string|null $tb
     * @param string|null $a
     * @param string|null $b
     * @param string|null $als
     * @param string|null $scp
     * @return true
     */
    public function setInnerJoin($tb = null, $a = null, $b = null, $als = null, $scp = null)
    {
        $this->_innerJoins = [];
        if ($tb && $a && $b) {
            $this->addInnerJoin($tb, $a, $b, $als, $scp);
        }
        return true;
    }

    /**
     * LEFT JOIN句を生成する
     * @param Dsn|null $dsn
     * @param array $join
     * @param string $joinType
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * } | null
     */
    public function _join(array $join, string $joinType = "LEFT", ?array $dsn = null): ?array
    {
        $q = '';
        $params = [];

        if (count($join) > 0) {
            $tablePrefix = isset($dsn['prefix']) ? $dsn['prefix'] : '';
            foreach ($join as $data) {
                $A = $data['a'];
                $B = $data['b'];
                $W = $data['where'];
                $q .= " {$joinType} JOIN";
                if (self::isClass($data['table'], 'SQL_Select')) {
                    $selectQuery = $data['table'];
                    $subQuerySQL = $selectQuery->getSQL($dsn);
                    $q .= "({$subQuerySQL})";
                    $params = array_merge($params, $selectQuery->getParams());
                } else {
                    $q .= ' ' . self::quoteKey($tablePrefix . $data['table']);
                }
                if ($scp = $A->getScope()) {
                    $scp = self::quoteKey($scp);
                    $q .= " AS {$scp}";
                }
                if (is_null($W)) {
                    $where = '';
                } else {
                    $whereSQL = $W->getSQL($dsn);
                    $params = array_merge($params, $W->getParams());
                    $where = " AND {$whereSQL}";
                }
                $q .= " ON {$A->getSQL($dsn)} = {$B->getSQL($dsn)}{$where}";
            }
            return [
                'sql' => $q,
                'params' => $params,
            ];
        }
        return null;
    }

    /**
     * JOIN句を生成する
     *
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    protected function join(?array $dsn = null): array
    {
        $sql = '';
        $params = [];
        $leftJoin = $this->_join($this->_leftJoins, 'LEFT', $dsn);
        if ($leftJoin) {
            $sql .= $leftJoin['sql'];
            $params = $leftJoin['params'];
        }
        $innerJoin = $this->_join($this->_innerJoins, 'INNER', $dsn);
        if ($innerJoin) {
            $sql .= $innerJoin['sql'];
            $params = array_merge($params, $innerJoin['params']);
        }
        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }
}
