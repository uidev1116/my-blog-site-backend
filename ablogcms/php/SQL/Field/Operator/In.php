<?php

class SQL_Field_Operator_In extends SQL_Field_Operator
{
    /**
     * @var bool
     */
    public $_not = false;

    /**
     * NOT IN句を生成するかどうかを設定する。
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
        $left = null;
        $field = $this->getField();
        if (self::isClass($field, 'SQL')) {
            $left = $field->getSQL($dsn);
            $params = $field->getParams();
        } else {
            $left = $this->_field($dsn);
        }
        if (!$left || !is_string($left)) {
            throw new InvalidArgumentException('Field is not set for SQL_Field_Operator_In');
        }
        $placeholder = self::safePlaceholder($left);
        $opr = $this->_not ? 'NOT IN' : 'IN';

        $opr = $this->_not ? 'NOT IN' : 'IN';
        if (self::isClass($this->_value, 'SQL_Select')) {
            $subQuerySql = $this->_value->getSQL($dsn);
            if ($subQuerySql) {
                $sql = "$left {$opr} ($subQuerySql)";
                $params = $this->_value->getParams($dsn);
            } else {
                throw new InvalidArgumentException("Subquery SQL is not set for IN operator");
            }
        } else {
            $expr = self::$connection->createQueryBuilder()->expr();
            $right = $this->_right($dsn);
            $val = $right['val'];
            if (!is_array($val)) {
                throw new InvalidArgumentException('Value for SQL_Field_Operator_In must be an array');
            }
            if (!$val) {
                return [
                    'sql' => '',
                    'params' => [],
                ];
            }
            $placeholders = [];
            foreach ($val as $i => $data) {
                $paramName = "{$placeholder}_$i";
                $placeholders[] = ":$paramName";
                $params[$paramName] = $data;
            }
            if ($opr === 'IN') {
                $sql = $expr->in($left, implode(', ', $placeholders));
            } elseif ($opr === 'NOT IN') {
                $sql = $expr->notIn($left, implode(', ', $placeholders));
            } else {
                throw new InvalidArgumentException("Unsupported operator: {$opr}");
            }
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }
}
