<?php

class SQL_Field_Operator_Exists extends SQL_Field_Operator
{
    /**
     * @var bool
     */
    public $_not = false;

    /**
     * NOT EXISTS句を生成するかどうかを設定する。
     *
     * @param bool $not
     * @return void
     */
    public function setNot($not)
    {
        $this->_not = $not;
    }

    /**
     * @return bool
     */
    public function getNot()
    {
        return $this->_not;
    }

    /**
     * @inheritDoc
     */
    protected function _operator($dsn = null)
    {
        $params = [];
        $opr = $this->_not ? 'NOT EXISTS' : 'EXISTS';

        if (self::isClass($this->_value, 'SQL_Select')) {
            $subQuerySql = $this->_value->getSQL($dsn);
            if ($subQuerySql) {
                $sql = "{$opr} ($subQuerySql)";
                $params = $this->_value->getParams($dsn);
            } else {
                throw new InvalidArgumentException("Subquery SQL is not set for EXISTS operator");
            }
        } else {
            throw new InvalidArgumentException("Value for EXISTS operator must be a SQL_Select instance");
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }
}
