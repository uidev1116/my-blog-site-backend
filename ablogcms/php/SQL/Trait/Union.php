<?php

/**
 * SQL_Trait_Union
 *
 * SQLのUNION句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Union
{
    /**
     * @var \SQL_Select[]
     */
    public $_union = [];

    /**
     * @param \SQL_Select $select
     * @return void
     */
    public function addUnion($select)
    {
        $this->_union[] = $select;
    }

    /**
     * @param \SQL_Select[] $selects
     * @return void
     */
    public function setUnion($selects)
    {
        $this->_union = $selects;
    }

    /**
     * UNION句を生成する
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * } | null
     */
    protected function union(?array $dsn = null): ?array
    {
        if (count($this->_union) === 0) {
            return null;
        }
        $q = '';
        $params = [];
        foreach ($this->_union as $sql) {
            $q .= " UNION (\n{$sql->getSQL($dsn)}\n)";
            $params = array_merge($params, $sql->getParams($dsn));
        }
        return [
            'sql' => $q,
            'params' => $params,
        ];
    }
}
