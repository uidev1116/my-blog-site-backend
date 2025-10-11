<?php

class FilterCondition
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @var '='|'<>'|'<'|'<='|'>'|'>='|'LIKE'|'NOT LIKE'|'REGEXP'|'NOT REGEXP'
     */
    private $operator;

    /**
     * @var 'OR'|'AND'
     */
    private $glue;

    /**
     * @param mixed $value
     * @param '='|'<>'|'<'|'<='|'>'|'>='|'LIKE'|'NOT LIKE'|'REGEXP'|'NOT REGEXP' $operator
     * @param 'OR'|'AND' $glue
     */
    public function __construct(
        $value,
        string $operator,
        string $glue
    ) {
        $this->value = $value;
        if (!in_array($operator, ['=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'REGEXP', 'NOT REGEXP'], true)) {
            throw new InvalidArgumentException('Invalid operator');
        }
        $this->operator = $operator;
        if (!in_array($glue, ['OR', 'AND'], true)) {
            throw new InvalidArgumentException('Invalid glue');
        }
        $this->glue = $glue;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return '='|'<>'|'<'|'<='|'>'|'>='|'LIKE'|'NOT LIKE'|'REGEXP'|'NOT REGEXP'
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return 'OR'|'AND'
     */
    public function getGlue(): string
    {
        return $this->glue;
    }

    /**
     * Field_Search の指定したフィールドからFilterConditionの配列を生成する
     * @param Field_Search $fieldSearch
     * @param string $fieldName
     * @return FilterCondition[]
     */
    public static function fromFieldSearch(Field_Search $fieldSearch, string $fieldName): array
    {
        $conditions = [];
        $operators = $fieldSearch->getOperator($fieldName, null);
        foreach ($operators as $i => $operator) {
            $value = $fieldSearch->get($fieldName, '', $i);
            if (
                $value === '' &&
                $operator !== 'em' &&
                $operator !== 'nem'
            ) {
                continue;
            }

            switch ($operator) {
                case 'eq':
                    $operator   = '=';
                    $value      = strval($value);
                    break;
                case 'neq':
                    $operator   = '<>';
                    $value      = strval($value);
                    break;
                case 'lt':
                    $operator   = '<';
                    $value      = is_numeric($value) ? ( ( $value == intval($value) ) ? intval($value) : floatval($value) ) : $value;
                    break;
                case 'lte':
                    $operator   = '<=';
                    $value      = is_numeric($value) ? ( ( $value == intval($value) ) ? intval($value) : floatval($value) ) : $value;
                    break;
                case 'gt':
                    $operator   = '>';
                    $value      = is_numeric($value) ? ( ( $value == intval($value) ) ? intval($value) : floatval($value) ) : $value;
                    break;
                case 'gte':
                    $operator   = '>=';
                    $value      = is_numeric($value) ? ( ( $value == intval($value) ) ? intval($value) : floatval($value) ) : $value;
                    break;
                case 'lk':
                    $operator   = 'LIKE';
                    $value      = strval($value);
                    break;
                case 'nlk':
                    $operator   = 'NOT LIKE';
                    $value      = strval($value);
                    break;
                case 're':
                    $operator   = 'REGEXP';
                    break;
                case 'nre':
                    $operator   = 'NOT REGEXP';
                    break;
                case 'nem':
                    $operator   = '<>';
                    $value      = '';
                    break;
                case 'em':
                    $operator   = '=';
                    $value      = '';
                    break;
                default:    // exception
                    continue 2;
            }
            if (!is_numeric($value)) {
                $value = preg_replace('/\\\(.)/u', '${1}', $value); // エスケープを考慮
            }
            if ($operator === 'LIKE' and !preg_match('@^%|%$@', $value)) {
                $value = '%' . $value . '%';
            }
            $connector = $fieldSearch->getConnector($fieldName, $i) ?? 'AND';
            if (is_array($connector)) {
                $connector = strtoupper($connector[0] ?? 'AND');
            }
            $glue = strtoupper($connector) === 'OR' ? 'OR' : 'AND';
            $conditions[] = new self($value, $operator, $glue);
        }
        return $conditions;
    }
}
