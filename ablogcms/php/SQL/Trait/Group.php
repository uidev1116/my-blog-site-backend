<?php

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * SQL_Trait_Group
 *
 * SQLのGROUP BY句を生成するためのトレイト
 *
 * @package php
 */
trait SQL_Trait_Group
{
    /**
     * @var \SQL_Field[]
     */
    public $_groups = [];


    /**
     * 指定されたfieldでGROUP BY句を生成する<br>
     * $SQL->addGroup('blog_id', 'acms_blog');<br>
     * GROUP BY acms_blog.blog_id
     *
     * @param \SQL_Field|string $fd
     * @param string|null $scp
     * @return true
     */
    public function addGroup($fd, $scp = null)
    {
        $this->_groups[] = self::isClass($fd, 'SQL_Field') ? $fd : SQL::newField($fd, $scp);
        return true;
    }

    /**
     * @param \SQL_Field|string|null $fd
     * @param string|null $scp
     * @return true
     */
    public function setGroup($fd = null, $scp = null)
    {
        $this->_groups  = [];
        if ($fd) {
            $this->addGroup($fd, $scp);
        }
        return true;
    }

    /**
     * Group By句を生成する
     * @param QueryBuilder $qb
     * @param Dsn|null $dsn
     * @return void
     */
    public function group(QueryBuilder $qb, ?array $dsn = null): void
    {
        if (count($this->_groups) === 0) {
            return;
        }
        $first = true;
        foreach ($this->_groups as $sql) {
            if ($first) {
                $qb->groupBy($sql->getSQL($dsn));
                $first = false;
            } else {
                $qb->addGroupBy($sql->getSQL($dsn));
            }
        }
    }
}
