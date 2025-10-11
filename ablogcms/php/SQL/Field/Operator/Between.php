<?php

/**
 * @template T of string|int|float|null
 */
class SQL_Field_Operator_Between extends SQL_Field_Operator
{
    /**
     * @var T
     */
    public $_a = null;

    /**
     * @var T
     */
    public $_b = null;

    /**
     * 指定されたa, bからBETWEEN句を生成する。<br>
     * $SQL->setBetween(10, 20);<br>
     * BETWEEN 10 AND 20
     * @param T $a
     * @param T $b
     * @return true
     */
    public function setBetween($a, $b)
    {
        $this->_a   = $a;
        $this->_b   = $b;
        return true;
    }

    /**
     * @return T[]
     */
    public function getBetween()
    {
        return [$this->_a, $this->_b];
    }

    /**
     * @inheritDoc
     */
    protected function _operator($dsn = null)
    {
        $params = [];
        $left = null;
        if (self::isClass($this->_field, 'SQL_Field')) {
            $left = $this->_field->getSQL($dsn);
            $params = $this->_field->getParams();
        } else {
            $left = $this->_field($dsn);
        }
        if (!$left || !is_string($left)) {
            throw new InvalidArgumentException('Field is not set for SQL_Field_Operator_Between');
        }
        if (!$this->_a || !$this->_b) {
            throw new InvalidArgumentException('Between values are not set for SQL_Field_Operator_Between');
        }
        $placeholder = self::safePlaceholder($left);
        $minPlaceholder = "min_{$placeholder}";
        $maxPlaceholder = "max_{$placeholder}";

        $sql = "$left BETWEEN :$minPlaceholder AND :$maxPlaceholder";
        $params[$minPlaceholder] = $this->_a;
        $params[$maxPlaceholder] = $this->_b;

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }
}
