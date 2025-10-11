<?php

class SQL_Field_Operator extends SQL_Field_Function
{
    /**
     * @var SQL|array|string|int|float|null
     */
    public $_value = null;

    /**
     * @var string|null
     */
    public $_operator  = null;

    /**
     * @param SQL|array|string|int|float|null $val
     * @return true
     */
    public function setValue($val)
    {
        $this->_value = $val;
        return true;
    }

    /**
     * @return SQL|array|string|int|float|null
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * @param string $opr
     * @return true
     */
    public function setOperator($opr)
    {
        $this->_operator = $opr;
        return true;
    }

    /**
     * @return string|null
     */
    public function getOperator()
    {
        return $this->_operator;
    }

    /**
     * @param Dsn|null $dsn
     * @return array{
     *  val: array|string|int|float|null,
     *  opr: string|null,
     *  isPlaceholder?: bool
     * }
     */
    protected function _right($dsn = null): array
    {
        $val = $this->_value;
        $opr = $this->_operator;
        $isPlaceholder = true;

        if (SQL::isClass($val, 'SQL')) {
            $val = $val->getSQL($dsn);
            $isPlaceholder = false;
        }
        return [
            'val' => $val,
            'opr' => $opr,
            'isPlaceholder' => $isPlaceholder,
        ];
    }

    /**
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    protected function _operator($dsn = null)
    {
        $params = [];
        $left = null;
        $field = $this->getField();
        if (SQL::isClass($field, 'SQL')) {
            $left = $field ->getSQL($dsn);
            $params = $field ->getParams();
        } else {
            $left = $this->_field($dsn);
        }
        if (!$left || !is_string($left)) {
            throw new InvalidArgumentException('Field is not set for SQL_Field_Operator');
        }
        $right = $this->_right($dsn);
        $placeholder = self::safePlaceholder($left);
        $isPlaceholder = $right['isPlaceholder'] ?? true;
        $expr = self::$connection->createQueryBuilder()->expr();
        $sql = '';
        $opr = strtoupper($right['opr'] ?? '=');
        $val = $right['val'];
        if (is_array($val)) {
            throw new InvalidArgumentException('Value for SQL_Field_Operator cannot be an array');
        }
        $allowedOprs = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'REGEXP', 'NOT REGEXP', '+', '-'];
        if (!in_array(strtoupper($opr), $allowedOprs, true)) {
            throw new InvalidArgumentException("Unsupported operator: {$opr}");
        }
        if ($val === null) {
            if ($opr === '=') {
                $sql = $expr->isNull($left);
            } else {
                $sql = $expr->isNotNull($left);
            }
        } else {
            if ($isPlaceholder) {
                $numberLeft = is_numeric($val) ? "({$left} + 0)" : $left;
                switch ($opr) {
                    case '=':
                        $sql = $expr->eq($left, ":{$placeholder}");
                        break;
                    case '!=':
                    case '<>':
                        $sql = $expr->neq($left, ":{$placeholder}");
                        break;
                    case '<':
                        $sql = $expr->lt($numberLeft, ":{$placeholder}");
                        break;
                    case '<=':
                        $sql = $expr->lte($numberLeft, ":{$placeholder}");
                        break;
                    case '>':
                        $sql = $expr->gt($numberLeft, ":{$placeholder}");
                        break;
                    case '>=':
                        $sql = $expr->gte($numberLeft, ":{$placeholder}");
                        break;
                    case 'LIKE':
                        $sql = $expr->like($left, ":{$placeholder}");
                        break;
                    case 'NOT LIKE':
                        $sql = $expr->notLike($left, ":{$placeholder}");
                        break;
                    case 'REGEXP':
                        $sql = "{$left} REGEXP :{$placeholder}";
                        break;
                    case 'NOT REGEXP':
                        $sql = "{$left} NOT REGEXP :{$placeholder}";
                        break;
                    case '+':
                        $sql = "{$left} + :{$placeholder}";
                        break;
                    case '-':
                        $sql = "{$left} - :{$placeholder}";
                        break;
                    default:
                }
                $params["$placeholder"] = $val;
            } else {
                $val = (string) $val;
                $sql = "$left {$opr} {$val}";
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
    public function get($dsn = null): array
    {
        return $this->_operator($dsn);
    }
}
