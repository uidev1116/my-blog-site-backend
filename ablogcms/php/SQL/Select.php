<?php

/**
 * SQL_Select
 *
 * SQLヘルパのSelectメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Select extends SQL_Where
{
    use SQL_Trait_Union;
    use SQL_Trait_Group;
    use SQL_Trait_Limit;
    use SQL_Trait_Order;
    use SQL_Trait_Having;

    /**
     * @var array{
     *  table: \SQL_Select|\SQL_Field|string,
     *  alias: string|null
     * }[]
     */
    public $_tables = [];

    /**
     * @var array{
     *  field: \SQL_Field_Function,
     *  alias: string|null
     * }[]
     */
    public $_selects = [];

    /**
     * @param \SQL_Select|\SQL_Field|string $tb
     * @param string|null $als
     * @param bool $straight_join
     * @return true
     */
    public function addTable($tb, $als = null, $straight_join = false)
    {
        $this->_straightJoin = $straight_join;
        $this->_tables[] = [
            'table' => $tb,
            'alias' => $als,
        ];
        return true;
    }

    /**
     * @param \SQL_Select|\SQL_Field|string|null $tb
     * @param string|null $als
     * @param bool $straight_join
     * @return true
     */
    public function setTable($tb = null, $als = null, $straight_join = false)
    {
        $this->_tables  = [];
        if ($tb) {
            $this->addTable($tb, $als, $straight_join);
        }
        return true;
    }

    /**
     * 指定されたfieldを追加する。<br>
     * $SQL->addSelect('entry_id', 'entry_count', 'acms_entry', 'count');<br>
     * SELECT COUNT(acms_entry.entry_id) AS entry_count
     *
     * @param SQL_Field|string $fd
     * @param string|null $als
     * @param string|null $scp
     * @param array|string|null $func
     * @return true
     */
    public function addSelect($fd, $als = null, $scp = null, $func = null)
    {
        $F = new SQL_Field_Function();
        $F->setField($fd);
        $F->setScope($scp);
        $F->setFunction($func);

        $this->_selects[]   = [
            'field' => $F,
            'alias' => $als,
        ];
        return true;
    }

    /**
     * @param SQL_Field|string|null $fd
     * @param string|null $als
     * @param string|null $scp
     * @param array|string|null $func
     * @return true
     */
    public function setSelect($fd = null, $als = null, $scp = null, $func = null)
    {
        $this->_selects = [];
        if ($fd) {
            $this->addSelect($fd, $als, $scp, $func);
        }
        return true;
    }

    /**
     * @param string $fd
     * @param string|float $lng
     * @param string|float $lat
     * @param string|null $als
     * @param string|null $scp
     * @return true
     */
    public function addGeoDistance($fd, $lng, $lat, $als = null, $scp = null)
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric values.');
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        $fd = SQL::quoteKey($fd);
        $pointSQL = sprintf("ST_GeomFromText('POINT(%F %F)')", $lng, $lat);
        $select = SQL::newField(
            "ROUND(ST_Distance_Sphere({$fd}, {$pointSQL}))",
            null,
            false
        );
        $this->addSelect($select, $als, $scp);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        if (!$this->_tables) {
            throw new InvalidArgumentException('Table name is not set for SQL_Select');
        }
        $tablePrefix = $dsn['prefix'] ? $dsn['prefix'] : '';
        $qb = self::$connection->createQueryBuilder();

        // select
        if ($this->_selects) {
            $first = true;
            foreach ($this->_selects as $s) {
                $column = $s['field'];
                $field = $column->getSQL($dsn);
                foreach ($column->getParams() as $key => $value) {
                    $qb->setParameter($key, $value);
                }
                if (isset($s['alias']) && $s['alias']) {
                    $alias = self::quoteKey($s['alias']);
                    $field = "{$field} AS {$alias}";
                }
                if ($first) {
                    $qb->select($field);
                    $first = false;
                } else {
                    $qb->addSelect($field);
                }
            }
        } else {
            $qb->select('*');
        }

        // join
        $join = $this->join($dsn);
        $joinSQL = $join['sql'];
        foreach ($join['params'] as $key => $value) {
            $qb->setParameter($key, $value);
        }

        // union
        $union = $this->union($dsn);
        $unionSQL = '';
        if ($union) {
            $unionSQL = $union['sql'];
            foreach ($union['params'] as $key => $value) {
                $qb->setParameter($key, $value);
            }
        }

        // from
        foreach ($this->_tables as $t) {
            $table = $t['table'];
            $alias = isset($t['alias']) && $t['alias'] ? $t['alias'] : null;
            $alias = $alias ? 'AS ' . self::quoteKey($alias) : '';

            if (self::isClass($table, 'SQL')) {
                $subQuerySql = $table->getSQL($dsn);
                if ($subQuerySql) {
                    $parts = ["({$subQuerySql})"];
                    if ($alias) {
                        $parts[] = $alias;
                    }
                    if ($joinSQL) {
                        $parts[] = $joinSQL;
                    }
                    if ($unionSQL) {
                        $parts[] = $unionSQL;
                    }
                    $qb->from(implode(' ', $parts));
                    // サブクエリのパラメータもマージして渡す
                    $subQueryParams = $table->getParams($dsn);
                    foreach ($subQueryParams as $key => $val) {
                        $qb->setParameter($key, $val);
                    }
                }
            } elseif (is_string($table)) {
                $table = self::quoteKey($tablePrefix . $table);
                $parts = [$table];
                if ($alias) {
                    $parts[] = $alias;
                }
                if ($joinSQL) {
                    $parts[] = $joinSQL;
                }
                if ($unionSQL) {
                    $parts[] = $unionSQL;
                }
                $qb->from(implode(' ', $parts));
            }
        }

        //-------
        // where
        if (count($this->_wheres) > 0) {
            $where = $this->where($dsn);
            $whereSql = $where['sql'];
            if ($whereSql) {
                $whereParams = $where['params'];
                $qb->where($whereSql);
                foreach ($whereParams as $key => $value) {
                    $qb->setParameter($key, $value);
                }
            }
        }

        //-------
        // group
        $this->group($qb, $dsn);

        //--------
        // having
        $havingParams = $this->having($qb, $dsn);
        foreach ($havingParams as $key => $value) {
            $qb->setParameter($key, $value);
        }

        //-------
        // order
        $this->order($qb, $dsn);

        //-------
        // limit
        $this->limit($qb);

        return [
            'sql' => $qb->getSQL(),
            'params' => $qb->getParameters(),
        ];
    }
}
