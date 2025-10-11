<?php

class SQL_Field_Function extends SQL_Field
{
    /**
     * @var array|null
     */
    public $_args  = null;

    /**
     * SQL関数を設定する。<br>
     * 引数には、配列 or 文字列を指定する。<br>
     * 配列の場合、最初の要素は関数名、それ以降は引数となる。<br>
     * $sqlFunction->setField('entry_title');<br>
     * $sqlFunction->setFunction(['SUBSTR', 0, 10]);<br>
     * SUBSTR(entry_title, 0, 10)<br>
     *
     * 文字列の場合、その文字列が関数名として渡される<br>
     * $sqlFunction->setField('entry_id');<br>
     * $sqlFunction->setFunction('COUNT');<br>
     * COUNT(entry_id)
     *
     * @param array|string|int|null $args
     * @return true
     */
    public function setFunction($args)
    {
        $this->_args = is_array($args) ? $args : func_get_args();
        return true;
    }

    /**
     * @return array|null
     */
    public function getFunction($func)
    {
        return $this->_args;
    }

    /**
     * @param Dsn|null $dsn
     * @return array{
     *  sql: string,
     *  params: list<mixed>|array<string, mixed>,
     * }
     */
    protected function _function($dsn = null)
    {
        $params = [];
        $field = $this->getField();
        if (self::isClass($field, 'SQL')) {
            $q = $field->getSQL($dsn);
            foreach ($field->getParams() as $key => $value) {
                $params[$key] = $value;
            }
        } else {
            $q = $this->_field($dsn);
        }
        if ($this->_args[0]) {
            // 関数名のネスト対応（例: COUNT,DISTINCT → ['COUNT', 'DISTINCT']）
            $funcs = array_map('strtoupper', explode(',', $this->_args[0]));

            // エイリアス対応
            $funcs = array_map(function ($f) {
                return match ($f) {
                    'SUBSTR' => 'SUBSTRING',
                    'RANDOM' => 'RAND',
                    default => $f,
                };
            }, $funcs);

            foreach ($funcs as $f) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $f)) {
                    throw new InvalidArgumentException("Invalid function name: {$f}");
                }
            }

            // 最終的な関数適用対象の初期値
            $inner = $q;

            // SUBSTRING など、特殊な処理が必要な関数を事前処理
            $specialFunc = $funcs[array_key_last($funcs)];
            if ($specialFunc === 'SUBSTRING') {
                $inner = $specialFunc . '(' . $inner;
                if (array_key_exists(1, $this->_args)) {
                    $arg = intval($this->_args[1]) + 1;
                    $inner .= ', ' . $arg;
                    if (array_key_exists(2, $this->_args)) {
                        $arg = intval($this->_args[2]);
                        $inner .= ', ' . $arg;
                    }
                }
                $inner .= ')';
                array_pop($funcs); // もう処理したので取り除く
            } else {
                // 通常の関数引数追加
                for ($i = 1; array_key_exists($i, $this->_args); $i++) {
                    $arg = $this->_args[$i];
                    if (is_null($arg)) {
                        $arg = 'NULL';
                    } elseif (is_string($arg)) {
                        $arg = DB::quote($arg);
                    }
                    $inner .= ', ' . $arg;
                }
                if (count($this->_args) > 1) {
                    $inner = "{$specialFunc}({$inner})";
                    array_pop($funcs);
                }
            }
             // 残りの関数でネストを作る（外側から順に wrap）
            while ($func = array_pop($funcs)) {
                if ($func === 'DISTINCT') {
                    $inner = "DISTINCT {$inner}";
                } else {
                    $inner = "{$func}({$inner})";
                }
            }
            $q = $inner;
        }

        return [
            'sql' => (string) $q,
            'params' => $params,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null): array
    {
        return $this->_function($dsn);
    }
}
