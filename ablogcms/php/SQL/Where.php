<?php

/**
 * SQL_Where
 *
 * SQLヘルパのWhereメソッド群です。<br>
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 *
 * @package php
 */
class SQL_Where extends SQL
{
    use SQL_Trait_Join;

    /**
     * @var array{
     *   where: SQL|string|int,
     *   glue: 'AND' | 'OR'
     * }[]
     */
    public $_wheres    = [];

    /**
     * @param SQL|string|int $w
     * @param 'AND' | 'OR' $gl
     */
    public function addWhere($w, $gl = 'AND')
    {
        $this->_wheres[]    = [
            'where' => $w,
            'glue'  => $gl,
        ];
        return true;
    }

    /**
     * @param SQL|string|int $w
     * @param 'AND' | 'OR' $gl
     */
    public function setWhere($w, $gl = 'AND')
    {
        $this->_wheres  = [];
        if ($w) {
            $this->addWhere($w, $gl);
        }
        return true;
    }

    /**
     * @param \SQL_Field|string $fd
     * @param \SQL_Field|string|int|float|null $val
     * @param string $opr
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return array{
     *   where: \SQL_Field_Operator,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereOpr($fd, $val, $opr = '=', $gl = 'AND', $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $F = $fd;
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $F = new SQL_Field_Function();
            $F->setField($fd);
            $F->setFunction($func);
        } else {
            $F = new SQL_Field_Function();
            $F->setField($fd);
            $F->setScope($scp);
            $F->setFunction($func);
        }

        return  [
            'where' => self::newOpr($F, $val, $opr),
            'glue'  => $gl,
        ];
    }

    /**
     * @param \SQL_Field|string $fd
     * @param array|SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return array{
     *   where: \SQL_Field_Operator_In,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereIn($fd, $vals, $gl = 'AND', $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $F  = $fd;
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $F  = new SQL_Field_Function($fd);
            $F->setFunction($func);
        } else {
            $F  = new SQL_Field_Function();
            $F->setField($fd);
            $F->setScope($scp);
            $F->setFunction($func);
        }

        return [
            'where' => self::newOprIn($F, $vals),
            'glue'  => $gl,
        ];
    }

    /**
     * @param \SQL_Field|string $fd
     * @param array|SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return array{
     *   where: \SQL_Field_Operator_In,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereNotIn($fd, $vals, $gl = 'AND', $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $F  = $fd;
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $F  = new SQL_Field_Function($fd);
            $F->setFunction($func);
        } else {
            $F  = new SQL_Field_Function();
            $F->setField($fd);
            $F->setScope($scp);
            $F->setFunction($func);
        }

        return [
            'where' => self::newOprNotIn($F, $vals),
            'glue'  => $gl,
        ];
    }

    /**
     * @param \SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @return array{
     *   where: \SQL_Field_Operator_Exists,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereExists($vals, $gl = 'AND')
    {
        return [
            'where' => self::newOprExists($vals),
            'glue'  => $gl,
        ];
    }

    /**
     * @param \SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @return array{
     *   where: \SQL_Field_Operator_Exists,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereNotExists($vals, $gl = 'AND')
    {
        return [
            'where' => self::newOprNotExists($vals),
            'glue'  => $gl,
        ];
    }

    /**
     * 指定されたfieldとa, bからBETWEEN句を生成する。<br>
     * $SQL->addWhereBw('entry_id', 10, 20, 'AND', 'entry', 'count');<br>
     * WHERE 1 AND COUNT(entry.entry_id) BETWEEN 10 AND 20
     *
     * @param \SQL_Field|string $fd
     * @param string|int $a
     * @param string|int $b
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return array{
     *   where: \SQL_Field_Operator_Between,
     *   glue: 'AND' | 'OR'
     * }
     */
    public function getWhereBw($fd, $a, $b, $gl = 'AND', $scp = null, $func = null)
    {
        if (self::isClass($fd, 'SQL_Field_Function')) {
            $F  = $fd;
        } elseif (self::isClass($fd, 'SQL_Field')) {
            $F  = new SQL_Field_Function($fd);
            $F->setFunction($func);
        } else {
            $F  = new SQL_Field_Function();
            $F->setField($fd);
            $F->setScope($scp);
            $F->setFunction($func);
        }

        return [
            'where' => self::newOprBw($F, $a, $b),
            'glue'  => $gl,
        ];
    }

    /**
     * 指定されたfieldとvalueからWHERE句を生成する。<br>
     * $SQL->addWhereOpr('entry_id', 10, '=', 'OR', 'entry', 'count');<br>
     * WHERE 0 OR COUNT(entry.entry_id) = 10
     *
     * @param \SQL_Field|string $fd
     * @param \SQL_Field|string|int|float|null $val
     * @param string $opr
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return bool
     */
    public function addWhereOpr($fd, $val, $opr = '=', $gl = 'AND', $scp = null, $func = null)
    {
        $this->_wheres[] = $this->getWhereOpr($fd, $val, $opr, $gl, $scp, $func);
        return true;
    }

    /**
     * 指定されたfieldとvalue(配列)からIN句を生成する。<br>
     * $SQL->addWhereIn('entry_id', array(10, 20, 30), 'AND', 'entry');<br>
     * WHERE 1 AND entry.entry_id IN (10, 29, 30)
     *
     * @param \SQL_Field|string $fd
     * @param array|SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return bool
     */
    public function addWhereIn($fd, $vals, $gl = 'AND', $scp = null, $func = null)
    {
        if (!$vals) {
            $vals = [-100];
        }
        $this->_wheres[]    = $this->getWhereIn($fd, $vals, $gl, $scp, $func);
        return true;
    }

    /**
     * 指定されたfieldとvalue(配列)からNOT IN句を生成する。<br>
     * $SQL->addWhereNotIn('entry_id', array(10, 20, 30), 'AND', 'entry');<br>
     * WHERE 1 AND entry.entry_id NOT IN (10, 29, 30)
     *
     * @param \SQL_Field|string $fd
     * @param array|SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return bool
     */
    public function addWhereNotIn($fd, $vals, $gl = 'AND', $scp = null, $func = null)
    {
        $this->_wheres[] = $this->getWhereNotIn($fd, $vals, $gl, $scp, $func);
        return true;
    }

    /**
     * 指定されたSQL_SelectオブジェクトからEXISTS句を生成する。<br>
     * $SQL->addWhereExists(SQL_SELECT);<br>
     * WHERE 1 AND EXISTS (SELECT * ...)
     *
     * @param \SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @return true
     */
    public function addWhereExists($vals, $gl = 'AND')
    {
        $this->_wheres[]    = $this->getWhereExists($vals, $gl);
        return true;
    }

    /**
     * 指定されたSQL_SelectオブジェクトからNOT EXISTS句を生成する。<br>
     * $SQL->addWhereExists(SQL_SELECT);<br>
     * WHERE 1 AND NOT EXISTS (SELECT * ...)
     *
     * @param \SQL_Select $vals
     * @param 'AND' | 'OR' $gl
     * @return true
     */
    public function addWhereNotExists($vals, $gl = 'AND')
    {
        $this->_wheres[]    = $this->getWhereNotExists($vals, $gl);
        return true;
    }

    /**
     * 指定されたfieldとvalue(２つ)からBETWEEN句を生成する。<br>
     * $SQL->addWhereOpr('entry_id', 10, 20, 'AND', 'entry');<br>
     * WHERE 1 AND entry.entry_id BETWEEN 100 AND 200
     *
     * @param \SQL_Field|string $fd
     * @param string|int $a
     * @param string|int $b
     * @param 'AND' | 'OR' $gl
     * @param string|null $scp
     * @param array|string|null $func
     * @return bool
     */
    public function addWhereBw($fd, $a, $b, $gl = 'AND', $scp = null, $func = null)
    {
        $this->_wheres[] = $this->getWhereBw($fd, $a, $b, $gl, $scp, $func);
        return true;
    }

    /**
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    protected function where($dsn = null)
    {
        $sql = '';
        $first = true;
        $params = [];

        if ($this->_wheres) {
            foreach ($this->_wheres as $where) {
                $w = $where['where'];
                $glue = strtoupper($where['glue']);
                if (!in_array($glue, ['AND', 'OR'], true)) {
                    throw new \InvalidArgumentException("Invalid glue operator: {$glue}");
                }
                if (self::isClass($w, 'SQL')) {
                    $whereSQL = $w->getSQL($dsn);
                    $fragment = SQL::isClass($w, 'SQL_Where') ? "({$whereSQL})" : $whereSQL;
                    if ($fragment) {
                        if ($first) {
                            $sql = $fragment;
                            $first = false;
                        } else {
                            $sql .= " {$glue} {$fragment}";
                        }
                        $whereParams = $w->getParams($dsn);
                        foreach ($whereParams as $key => $value) {
                            $params[$key] = $value;
                        }
                    }
                }
            }
        }
        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null)
    {
        return $this->where($dsn);
    }
}
