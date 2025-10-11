<?php

class SQL_Field_Case extends SQL_Field
{
    /**
     * @var array{
     *   when: SQL|string|int|float|null,
     *   then: SQL|string|int|float|null
     * }[]
     */
    public $_cases = [];

    /**
     * @var SQL|string|null
     */
    public $_simple = null;

    /**
     * @var SQL|string|float|int|null
     */
    public $_else = null;

    /**
     * 単純CASE式を設定する。<br>
     * $case->setSimple('entry_status');<br>
     * CASE entry_status
     *
     * @param SQL|string|null $exp
     * @return void
     */
    public function setSimple($exp)
    {
        $this->_simple  = $exp;
    }

    /**
     * ELSE句を設定する。<br>
     * $case->setElse('draft');<br>
     * ELSE 'draft'
     *
     * @param SQL|string|int|float|null $exp
     * @return void
     */
    public function setElse($exp)
    {
        $this->_else = $exp;
    }

    /**
     * WHEN句とTHEN句を追加する。<br>
     * $case->add(SQL::newOpr('entry_status', 1, '='), 'open');<br>
     * WHEN entry_status = 1 THEN 'open'
     *
     * @param SQL|string|int|float|null $when
     * @param SQL|string|int|float|null $then
     * @return true
     */
    public function add($when, $then)
    {
        $this->_cases[] = [
            'when' => $when,
            'then' => $then,
        ];
        return true;
    }

    /**
     * WHEN句とTHEN句を設定する。<br>
     * $case->add(SQL::newOpr('entry_status', 1, '='), 'open');<br>
     * WHEN entry_status = 1 THEN 'open'
     *
     * @param SQL|string|int|float|null $when
     * @param SQL|string|int|float|null $then
     * @return true
     */
    public function set($when = null, $then = null)
    {
        $this->_cases = [];
        if ($when) {
            $this->add($when, $then);
        }
        return true;
    }

    /**
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    protected function _case($dsn = null): array
    {
        if (!$this->_cases) {
            throw new InvalidArgumentException('No cases defined for SQL_Field_Case');
        }
        $params = [];
        $q = "\nCASE";

        if ($this->_simple) {
            $simple = $this->_simple;
            $simpleSql = self::isClass($simple, 'SQL') ? $simple->getSQL($dsn) : DB::quote($simple);
            if ($simpleSql) {
                $q .= " {$simpleSql}";
            } else {
                throw new InvalidArgumentException('Simple expression is not set for SQL_Field_Case');
            }
        }
        foreach ($this->_cases as $i => $case) {
            $when = $case['when'];
            if (self::isClass($when, 'SQL')) {
                list($whenSQL, $whenParams) = self::aliasPlaceholders($when->getSQL($dsn), $when->getParams($dsn), "case_when_{$i}");
                $params = array_merge($params, $whenParams);
            } elseif (is_string($when)) {
                $whenSQL = DB::quote($when);
            } elseif (is_numeric($when)) {
                $whenSQL = (string) $when;
            } else {
                throw new InvalidArgumentException('Invalid when expression type for SQL_Field_Case');
            }
            if (!$whenSQL) {
                throw new InvalidArgumentException('When expression is not set for SQL_Field_Case');
            }
            $then = $case['then'];
            $placeholder = self::safePlaceholder("case_then_{$i}");
            if (self::isClass($then, 'SQL')) {
                $q .= "\n WHEN {$whenSQL} THEN " . $then->getSQL($dsn);
                $params = array_merge($params, $then->getParams($dsn));
            } else {
                $q .= "\n WHEN {$whenSQL} THEN :{$placeholder}";
                $params[$placeholder] = $then;
            }
        }
        if (!is_null($this->_else)) {
            $else = $this->_else;
            $elsePlaceholder = self::safePlaceholder("case_else");
            if (self::isClass($else, 'SQL')) {
                $elseSQL = $else->getSQL($dsn);
                $params = array_merge($params, $else->getParams($dsn));
            } elseif (is_string($else)) {
                $elseSQL = strtoupper($else) === 'NULL' ? 'NULL' : ":{$elsePlaceholder}";
                if ($elseSQL !== 'NULL') {
                    $params[$elsePlaceholder] = $else;
                }
            } elseif (is_numeric($else)) {
                $elseSQL = ":{$elsePlaceholder}";
                $params[$elsePlaceholder] = $else;
            }
            $q .= "\n ELSE {$elseSQL}";
        }
        $q .= "\n END";

        return [
            'sql' => $q,
            'params' => $params,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        return $this->_case($dsn);
    }
}
