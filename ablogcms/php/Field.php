<?php

/**
 * PHP8.2対応以前にシリアライズされたFieldオブジェクトをデシリアライズすると、
 * PHP8.2以上の環境でエラーが発生するため、
 * #[\AllowDynamicProperties] を付与している。
 */
#[\AllowDynamicProperties]
class Field
{
    /**
     * @var array<string, array>
     */
    public $_aryField = [];

    /**
     * @var array<string, Field>
     */
    public $_aryChild = [];

    /**
     * @var array<string, array>
     */
    public $_aryMeta = [];

    /**
     * constructor
     *
     * @param Field|array<string, mixed>|string|null $Field
     * @param bool $isDeep
     */
    final public function __construct($Field = null, $isDeep = false)
    {
        $this->overload($Field, $isDeep);
    }

    /**
     * フィールドクエリを解析する
     * @param string $query
     * @return void
     */
    public function parse($query)
    {
        foreach (preg_split('@/\s*and\s*/@i', $query, -1, PREG_SPLIT_NO_EMPTY) as $data) {
            $s = preg_split('@/@i', $data, -1, PREG_SPLIT_NO_EMPTY);
            if ($s === false) {
                continue;
            }
            $key = array_shift($s);
            while ($val = array_shift($s)) {
                $this->addField($key, $val);
            }
        }
    }

    /**
     * オブジェクトを上書きする
     *
     * @param Field|array<string, mixed>|string $Field
     * @param bool $isDeep
     * @return void
     */
    public function overload($Field, $isDeep = false)
    {
        if (is_object($Field) and 'FIELD' == substr(strtoupper(get_class($Field)), 0, 5)) {
            foreach ($Field->listFields() as $fd) {
                $this->setField($fd, $Field->getArray($fd, true));
            }
            if ($isDeep) {
                foreach ($Field->listChildren() as $child) {
                    $Child  =& $Field->getChild($child);
                    $class  = get_class($Child);
                    $Child  = new $class($Child, $isDeep);
                    $this->addChild($child, $Child);
                }
            }
        } elseif (is_array($Field)) {
            foreach ($Field as $key => $val) {
                if (is_object($val)) {
                    if ('FIELD' != substr(strtoupper(get_class($val)), 0, 5)) {
                        continue;
                    }
                    $this->addChild($key, $val);
                } else {
                    if (is_array($val)) {
                        reset($val);
                        if (0 !== key($val)) {
                            $f = new Field($val);
                            $this->addChild($key, $f);
                            continue;
                        } else {
                            reset($val);
                        }
                    }
                    $this->setField($key, $val);
                }
            }
        } elseif (is_string($Field) and '' !== $Field) {
            $this->parse($Field);
        }
    }

    /**
     * シングルトンパターンでオブジェクトを生成する
     *
     * @static
     * @param string $key
     * @param null|Field $Field
     * @return Field
     */
    public static function & singleton($key, $Field = null)
    {
        static $aryField  = [];

        if (!isset($aryField[$key]) || $Field !== null) {
            $aryField[$key] = new Field($Field);
        }

        return $aryField[$key];
    }

    /**
     * シリアライズされた文字列を返す
     * @return string
     */
    public function serialize()
    {
        $res    = '';

        foreach ($this->listFields() as $fd) {
            if ($vals = $this->getArray($fd)) {
                $res    .= '/and/' . $fd . '/' . join('/', $vals);
            }
        }
        return substr($res, 5);
    }

    /**
     * 指定したフィールド名のフィールドがnullかどうかを判定する
     * $fdにnullを指定した場合は、フィールドが一つも存在しない場合にtrueを返す
     *
     * @param string|null $fd
     * @param int $i
     * @return bool
     */
    public function isNull($fd = null, $i = 0)
    {
        return is_null($fd) ? !count($this->_aryField) : !isset($this->_aryField[$fd][$i]);
    }

    /**
     * 指定したフィールド名のフィールドがフィールドグループかどうかを判定する
     * @param string $fd
     * @return bool
     */
    public function isGroup($fd)
    {
        return false;
    }

    /**
     * 指定した名前のフィールドが存在するかどうかを判定する
     * @param string $fd
     * @param int|null $i
     * @return bool
     */
    public function isExists($fd, $i = null)
    {
        if (!array_key_exists($fd, $this->_aryField)) {
            return false;
        }
        if (!is_null($i) and !array_key_exists($i, $this->_aryField[$fd])) {
            return false;
        }
        return true;
    }

    /**
     * 指定したフィールド名の値を取得する
     * フィールド名に文字列以外を指定した場合はfalseを返す
     * @template T
     * @param T $fd
     * @param string|int|null $def
     * @param int $i
     * @return (T is string ? string : false)
     */
    public function get($fd, $def = null, $i = 0)
    {
        if (!is_string($fd)) {
            return false;
        }
        $fdvalue = (!empty($this->_aryField[$fd][$i]) or (isset($this->_aryField[$fd][$i]) and ('0' === $this->_aryField[$fd][$i])))
                ? $this->_aryField[$fd][$i]
                : (!is_null($def) ? $def : (isset($this->_aryField[$fd][$i]) ? $this->_aryField[$fd][$i] : $def));

        return is_array($fdvalue) ? '' : strval($fdvalue);
    }

    /**
     * 指定したフィールド名の値を配列で取得する
     * @param string $fd
     * @param bool $strict falseの場合、空文字、null、フィールドグループを削除した配列を返す。デフォルトはfalse
     * @return array
     */
    public function getArray($fd, $strict = false)
    {
        if (!is_string($fd)) {
            return [];
        }
        $fds = isset($this->_aryField[$fd]) ? $this->_aryField[$fd] : [];
        if (!$cnt = count($fds)) {
            return [];
        }
        if (1 === $cnt and !isset($fds[0])) {
            return [];
        }

        if (!$strict) {
            for ($i = $cnt - 1; 0 <= $i; $i--) {
                if (!is_null($fds[$i]) and '' !== $fds[$i]) {
                    break;
                }
                if ($this->isGroup($fd)) {
                    break;
                }
                unset($fds[$i]);
            }
        }

        return $fds;
    }

    /**
     * フィールド名を列挙する
     * @return string[]
     */
    public function listFields()
    {
        return array_keys($this->_aryField);
    }

    /**
     * フィールドの値を設定する
     * @param string $fd フィールド名
     * @param array|string|int|float|null $vals
     * @return bool
     */
    public function setField($fd, $vals = null)
    {
        if (!is_string($fd)) {
            return false;
        }
        if (empty($vals) and 0 !== $vals and '0' !== $vals) {
            $this->_aryField[$fd]   = [];
        } else {
            if (!is_array($vals)) {
                $vals   = [$vals];
            }
            $this->_aryField[$fd]   = [];
            $max = max(array_keys($vals));
            $max = intval($max);
            for ($i = 0; $i <= $max; $i++) {
                $this->_aryField[$fd][$i] = isset($vals[$i]) ? $vals[$i] : '';
            }
        }
        return true;
    }

    /**
     * フィールドの値を設定する
     * @param string $fd フィールド名
     * @param array|string|int|float|null $vals
     * @return bool
     */
    public function set($fd, $vals = null)
    {
        return $this->setField($fd, $vals);
    }

    /**
     * 指定したフィールド名のフィールドに値を追加する
     * @param string $fd フィールド名
     * @param array|string|int|float|null $vals
     * @return bool
     */
    public function addField($fd, $vals)
    {
        if (!is_array($vals)) {
            $vals = [$vals];
        }
        foreach ($vals as $val) {
            $this->_aryField[$fd][] = $val;
        }
        return true;
    }

    /**
     * alias for addField
     *
     * @param string $fd フィールド名
     * @param array|string|int|float|null $vals
     * @return bool
     */
    public function add($fd, $vals)
    {
        return $this->addField($fd, $vals);
    }

    /**
     * 指定したフィールド名のフィールドを削除する
     * @param string $fd
     * @return bool
     */
    public function deleteField($fd)
    {
        if (!is_string($fd)) {
            return false;
        }
        unset($this->_aryField[$fd]);
        unset($this->_aryMeta[$fd]);
        return true;
    }

    /**
     * alias for deleteField
     *
     * @param string $fd
     * @return bool
     */
    public function delete($fd)
    {
        return $this->deleteField($fd);
    }

    /**
     * 指定した名前の子フィールドを取得する
     * @param string $name
     * @return Field
     */
    public function & getChild($name)
    {
        if (!isset($this->_aryChild[$name])) {
            $class  = get_class($this);
            $obj = new $class();
            $this->addChild($name, $obj);
        }
        return $this->_aryChild[$name];
    }

    /**
     * 指定した名前の子フィールドを設定する
     * @param string $name
     * @param Field &$Field
     * @return true
     */
    public function addChild($name, &$Field)
    {
        $this->_aryChild[$name] =& $Field;
        return true;
    }

    /**
     * 指定した名前の子フィールドを削除する
     * @return true
     */
    public function removeChild($name)
    {
        unset($this->_aryChild[$name]);
        return true;
    }

    /**
     * 子フィールド名を列挙する
     * @return string[]
     */
    public function listChildren()
    {
        return array_keys($this->_aryChild);
    }

    /**
     * 指定した名前の子フィールドが存在するかどうかを判定する
     * 名前を指定しない場合は、子フィールドが一つも存在しない場合にtrueを返す
     *
     * @param string|null $name
     * @return bool
     */
    public function isChildExists($name = null)
    {
        return is_null($name) ? !!count($this->_aryChild) : !!isset($this->_aryChild[$name]);
    }

    /**
     * 指定したフィールド名のフィールドにメタ情報を設定する
     * @param string $fd フィールド名
     * @param string|null $key メタ情報のキー
     * @param mixed $val メタ情報の値
     * @return true
     */
    public function setMeta($fd, $key = null, $val = null)
    {
        if (empty($key)) {
            $this->_aryMeta[$fd] = [];
        } else {
            $this->_aryMeta[$fd][$key] = $val;
        }
        return true;
    }

    /**
     * 指定したフィールド名のフィールドに設定されたメタ情報を取得する
     * $keyを指定しない場合は、指定したフィールド名のメタ情報すべてを配列で返す
     *
     * @template T of string|null
     * @param string $fd フィールド名
     * @param T $key メタ情報のキー
     * @return (T is non-empty-string ? mixed : array)
     */
    public function getMeta($fd, $key = null)
    {
        if (empty($key)) {
            return isset($this->_aryMeta[$fd]) ? $this->_aryMeta[$fd] : [];
        } else {
            return isset($this->_aryMeta[$fd][$key]) ? $this->_aryMeta[$fd][$key] : null;
        }
    }

    /**
     * 指定されたスコープ名に基づいてフィールド構造を深掘りし、関連するフィールドデータを整理します。
     *
     * 指定されたスコープ名の子フィールドを生成または取得し、
     * そのスコープ内で定義されているフィールドの値を元フィールドから新しい子フィールドに追加します。
     * 返り値として、新しい子フィールドを返します。
     *
     * @param string $scp スコープ名
     * @return Field 新たに生成された子フィールドの参照を返す
     */
    public function &dig($scp = 'field')
    {
        $Field  = $this->getChild($scp);

        if ($aryFd = $this->getArray($scp, true)) {
            foreach ($aryFd as $fd) {
                if (!$this->isExists($fd)) {
                    continue;
                }
                $Field->setField($fd, $this->getArray($fd));
                $this->deleteField($fd);
            }
            $this->deleteField($scp);
        }

        //-----------
        // reference
        if ($aryFd = $Field->listFields()) {
            foreach ($aryFd as $fd) {
                if ('&' !== substr($Field->get($fd), 0, 1)) {
                    continue;
                }
                $_fd    = preg_replace('@^\s*&\s*|\s*;$@', '', $Field->get($fd));
                if ($Field->isNull($_fd)) {
                    continue;
                }
                $Field->setField($fd, $Field->get($_fd));
            }
        }

        $this->addChild($scp, $Field);
        $Field  =& $this->getChild($scp);

        return $Field;
    }

    /**
     * カスタムユニットのフィールド名と値を調整します。
     *
     * フィールド名と値のペアを走査し、フィールド名に指定されたIDが含まれている場合に、
     * そのIDを除去して新しいフィールド名として設定します。IDがフィールド値にも含まれている場合（フィールド名が'@'で始まる場合）、
     * 値に対しても同様の処理を行います。
     *
     * 処理の結果、フィールド名からIDが除去された新しい配列が`_aryField`プロパティに設定されます。
     * また、メタデータを格納する`_aryMeta`プロパティは空の配列にリセットされます。
     *
     * @param string $id ユニットID。デフォルトは空文字列です。
     * @return void
     */
    public function retouchCustomUnit($id = '')
    {
        $aryField = [];
        $aryMeta = [];
        foreach ($this->_aryField as $key => $val) {
            $key = preg_replace("/^(.*)$id([^\d]*)$/", '$1$2', $key);
            if (preg_match('/^@/', $key)) {
                $val = preg_replace("/^(.*)$id([^\d]*)$/", '$1$2', $val);
            }
            $aryField[$key] = $val;
        }
        foreach ($this->_aryMeta as $key => $val) {
            $key = preg_replace("/^(.*)$id([^\d]*)$/", '$1$2', $key);
            $aryMeta[$key] = $val;
        }
        $this->_aryField = $aryField;
        $this->_aryMeta = $aryMeta;
    }

    /**
     * @param bool $isDeep 非推奨の引数です。使用しないでください。
     * @return true
     */
    public function reset(bool $isDeep = false)
    {
        return true;
    }

    /**
     * 指定したフィールドをコピーして、新しいオブジェクトを生成します。
     * @param string[] $fieldNames
     * @return static
     */
    public function cloneWith(array $fieldNames)
    {
        $field = new static();
        foreach ($fieldNames as $fieldName) {
            $values = $this->_aryField[$fieldName] ?? [];
            $meta = $this->_aryMeta[$fieldName] ?? [];
            $field->setField($fieldName, $values);
            foreach ($meta as $key => $value) {
                $field->setMeta($fieldName, $key, $value);
            }
        }
        return $field;
    }
}

class Field_Search extends Field
{
    /**
     * @var array<string, array<'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem' | null>>
     */
    public $_aryOperator = [];

    /**
     * @var array<string, array<'and' | 'or' | null>>
     */
    public $_aryConnector = [];

    /**
     * @var array<string, 'and'|'or'>
     */
    public $_arySeparator = [];

    /**
     * @inheritDoc
     */
    public function overload($Field, $isDeep = false)
    {
        if (!is_null($Field)) {
            parent::overload($Field, $isDeep);
            if ($Field instanceof Field_Search) {
                $this->_aryOperator     = $Field->_aryOperator;
                $this->_aryConnector    = $Field->_aryConnector;
                $this->_arySeparator    = $Field->_arySeparator;
            }
        }
    }

    /**
     * フィールド検索条件のクエリ文字列をパースする。
     *
     * クエリ書式は a-blog cms 独自の URL パス形式で、スラッシュ区切りで
     * フィールド名・connector・operator・value を順に並べる:
     *
     *   field/op/value                      ... 単一値（op 省略時は eq）
     *   field/op/value1/op/value2           ... 同一フィールドの連続値（connector は and）
     *   field/or/op/v1/or/op/v2             ... 同一フィールド内 OR 結合
     *   fieldA/v1/_and_/fieldB/v2           ... フィールド間 AND（_and_ セパレーター）
     *   fieldA/v1/_or_/fieldB/v2            ... フィールド間 OR
     *   fieldA/v1/and/fieldB/v2             ... 裸の and もフィールド間 AND として後方互換で受ける
     *
     * 重要な決まりごと:
     * - `and` トークンは parse では常に separator として扱う（connector ではない）。
     *   serialize 側で connector='and' を出力しない仕様（後述）と対になっており、
     *   出力に裸の and が現れない以上、入力で見た and は separator しかありえない。
     *   同一フィールド内の連続値は connector='and' を「省略形」として扱い、
     *   `field/op/v1/op/v2` のように and を書かずに表現する。
     * - 連続スラッシュ `//` は「演算子のみ・値なし」を意味する空トークンとして残し、
     *   末尾の `/` だけは無視する。
     */
    public function parse($query)
    {
        $tokens = preg_split('@(?<!\\\\)/@', $query);
        if ($tokens === false) {
            return;
        }

        // 末尾の空トークンは無視する（`price/200/300/` のような末尾スラッシュ対策）。
        // 途中の空トークン（連続 `//`）は値なし演算子の表現として保持する。
        while (count($tokens) > 0 && end($tokens) === '') {
            array_pop($tokens);
        }

        // 状態機械の 5 変数:
        //   field        ... 現在処理中のフィールド名（null = 次トークンをフィールド名として読む）
        //   connector    ... 値の結合演算子 'and' | 'or'（null = 未確定）
        //   operator     ... 比較演算子 'eq' | 'neq' | ...（null = 未確定）
        //   value        ... 値（null = 未確定、em/nem では '' に確定）
        //   tmpSeparator ... 直前に出現した separator を次フィールドに引き継ぐためのバッファ
        $field          = null;
        $connector      = null;
        $operator       = null;
        $value          = null;
        $tmpSeparator   = null;

        while (null !== ($token = array_shift($tokens))) {
            //-----------------------------------------------------------------
            // [1] フィールド名トークン
            //   field が null のとき、次のトークンは必ずフィールド名として読む。
            //   直前の separator（tmpSeparator）があればそれを採用し、
            //   なければデフォルトで 'and' separator を割り当てる。
            //-----------------------------------------------------------------
            if (is_null($field)) {
                $field      = $token;

                if (in_array($tmpSeparator, ['or', 'and'], true)) {
                    $this->addSeparator($field, $tmpSeparator);
                } else {
                    $this->addSeparator($field, 'and');
                }

                continue;
            }

            //-----------------------------------------------------------------
            // [2] 空トークン `//` のハンドリング
            //   connector が既に確定済みで operator がまだ未確定なら、
            //   eq とみなして「値なし eq フィルター」を作る（serialize の往復で
            //   `or//` のような表現が空 eq として復元されるようにするため）。
            //-----------------------------------------------------------------
            if ('' === $token) {
                if (!is_null($connector) && is_null($operator)) {
                    $operator   = 'eq';
                }
            }

            //-----------------------------------------------------------------
            // [3] operator トークンの判定
            //   fd/op/...      … connector 省略形（後段で 'and' を補う）
            //   fd/or/op/...   … connector 明示後の operator
            //-----------------------------------------------------------------
            if (is_null($operator)) {
                switch ($token) {
                    case 'eq':
                        // eq + 値 のときは「等値の列挙」を意味し、暗黙に OR で結ぶ仕様。
                        // 旧仕様との互換のため eq が現れた瞬間に connector を or に強制する。
                        $operator   = $token;
                        $connector  = 'or';
                        break;
                    case 'neq':
                    case 'lt':
                    case 'lte':
                    case 'gt':
                    case 'gte':
                    case 'lk':
                    case 'nlk':
                    case 're':
                    case 'nre':
                        $operator   = $token;
                        break;
                    case 'em':
                    case 'nem':
                        // em/nem は「値が空 / 空でない」を判定するため value を '' に固定する。
                        // 次のトークンを value として消費しないので、ここで value を確定させる。
                        $operator   = $token;
                        $value      = '';
                        break;
                }

                if (!is_null($operator)) {
                    // operator が確定したら connector のデフォルトを 'and' にする
                    // （eq の場合は上で既に 'or' になっているのでここでは上書きされない）。
                    if (is_null($connector)) {
                        $connector  = 'and';
                    }
                    // em/nem 以外は次トークンが value。それ以外（em/nem）は value 確定済みなので、
                    // 後続の値受け取りステップは飛ばして次トークンへ進む。
                    if (is_null($value)) {
                        continue;
                    }
                }
            }

            //-----------------------------------------------------------------
            // [4] connector トークンの判定
            //   ここで認識するのは `or` のみ。`and` は separator として後段で処理する。
            //   どちらでもないトークンが来た場合は「connector 省略の eq 値」と解釈し、
            //   connector='or' / operator='eq' / value=token をその場で確定させる。
            //-----------------------------------------------------------------
            if (is_null($connector)) {
                if ('or' === $token) {
                    $connector  = $token;
                    continue;
                } else {
                    // fd/val 形式（operator も connector も省略）
                    $connector  = 'or';
                    $operator   = 'eq';
                    $value      = $token;
                }
            }

            //-----------------------------------------------------------------
            // [5] value トークン と separator の振り分け
            //   value が未確定: 現トークンを value として確定。
            //   value 確定済み: 現トークンが and / _and_ / _or_ ならフィールド境界。
            //                   それ以外は通常 add に進む（値の重複追加など）。
            //-----------------------------------------------------------------
            if (is_null($value)) {
                if (is_null($operator)) {
                    $operator   = 'eq';
                }
                $value  = $token;
            } elseif (in_array($token, ['and', '_and_', '_or_'], true)) {
                // separator を検出: 次フィールドへの切り替えを準備する。
                // `_or_` だけ OR、それ以外は AND。状態変数をリセットして field を null に戻す。
                if ($token == '_or_') {
                    $tmpSeparator = 'or';
                } else {
                    $tmpSeparator = 'and';
                }

                $field      = null;
                $connector  = null;
                $operator   = null;
                $value      = null;

                continue;
            }

            $this->add($field, $value);
            $this->addOperator($field, $operator);
            $this->addConnector($field, $connector);

            $connector  = null;
            $operator   = null;
            $value      = null;
        }
    }

    /**
     * 指定したフィールド名のフィールドに対する結合子を追加する
     * @param string $fd
     * @param 'and' | 'or' $connector
     */
    public function addConnector($fd, $connector)
    {
        $this->_aryConnector[$fd][] = $connector;
    }

    /**
     * 指定したフィールド名のフィールドに対する演算子を追加する
     * @param string $fd
     * @param 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem' | null $operator
     */
    public function addOperator($fd, $operator)
    {
        $this->_aryOperator[$fd][] = $operator;
    }


    /**
     * 指定したフィールド名のフィールドに対する論理演算子を追加する
     * @param string $fd
     * @param 'and' | 'or' $separator
     */
    public function addSeparator($fd, $separator)
    {
        $this->_arySeparator[$fd] = $separator;
    }

    /**
     * 指定したフィールド名のフィールドに対する結合子を設定する
     * @param string $fd
     * @param 'and' | 'or' | null $connector
     */
    public function setConnector($fd, $connector = null)
    {
        if (is_null($connector)) {
            $this->_aryConnector[$fd] = [];
        } else {
            $this->_aryConnector[$fd] = [$connector];
        }
    }

    /**
     * 指定したフィールド名のフィールドに対する演算子を設定する
     * @param string $fd
     * @param 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem' | null $operator
     */
    public function setOperator($fd, $operator = null)
    {
        if (is_null($operator)) {
            $this->_aryOperator[$fd] = [];
        } else {
            $this->_aryOperator[$fd] = [$operator];
        }
    }

    /**
     * 指定したフィールド名のフィールドに対する結合子を取得する
     * 第2引数を指定しない場合は、フィールドに対する結合子の配列を返す
     *
     * @template T of int|null
     * @param string $fd フィールド名
     * @param T $i 結合子の指定 (省略可能)
     * @return (T is null ?
     *     array<'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem' | null> :
     *     'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem' | null
     * )
     */
    public function getOperator($fd, $i = 0)
    {
        return is_null($i) ?
            (!is_null($this->_aryOperator[$fd]) ? $this->_aryOperator[$fd] : null) :
            (isset($this->_aryOperator[$fd][$i]) ? $this->_aryOperator[$fd][$i] : null);
    }

    /**
     * 指定したフィールド名のフィールドに対する演算子を取得する
     * 第2引数を指定しない場合は、フィールドに対する演算子の配列を返す
     * @template T of int|null
     * @param string $fd
     * @param T $i
     * @return (T is null ? array<'and' | 'or' | null> : 'and' | 'or' | null)
     */
    public function getConnector($fd, $i = 0)
    {
        return is_null($i) ?
            (!is_null($this->_aryConnector[$fd]) ? $this->_aryConnector[$fd] : null) :
            (isset($this->_aryConnector[$fd][$i]) ? $this->_aryConnector[$fd][$i] : null);
    }

    /**
     * 指定したフィールド名のフィールドに対する論理演算子を取得する
     * @param string $fd
     * @return 'and' | 'or'
     */
    public function getSeparator($fd)
    {
        return isset($this->_arySeparator[$fd]) ? $this->_arySeparator[$fd] : 'and';
    }

    /**
     * @inheritDoc
     */
    public function delete($fd)
    {
        if (!is_string($fd)) {
            return false;
        }
        parent::delete($fd);
        unset($this->_aryOperator[$fd]);
        unset($this->_aryConnector[$fd]);
        unset($this->_arySeparator[$fd]);

        return true;
    }

    /**
     * フィールド検索条件を URL パス形式の文字列にシリアライズする。
     *
     * 出力上のルール:
     * - connector='and' は出力に現れない（同名フィールドの暗黙のデフォルトとして扱う）。
     *   裸の `and` を出すと parse 側が separator として解釈してしまうため、必ず省略する。
     * - connector='or' は明示的に出力する。
     * - operator='eq' は connector='or' と組み合わさったときに限り省略する
     *   （parse 側で eq は connector を or に強制する仕様の裏返し）。
     * - em / nem は値を持たないので value トークンを出さない。
     * - 空値を持つ連続フィルター（`or//or/op/val` のような表現）に備えて、
     *   空値の出現回数をカウント保留し、後続に値ありフィルターが現れた時点でまとめて出力する。
     * - フィールド境界は `_and_` / `_or_` で出力し、先頭フィールドにつくものだけ後段で除去する。
     */
    public function serialize()
    {
        $aryQuery   = [];

        foreach ($this->listFields() as $fd) {
            $aryValue       = $this->getArray($fd);
            $aryOperator    = $this->getOperator($fd, null);
            $aryConnector   = $this->getConnector($fd, null);
            $separator      = $this->getSeparator($fd);

            // 値・演算子・結合子のいずれも空ならこのフィールドは出力対象外
            if (!($cnt = max(count($aryValue), count($aryOperator), count($aryConnector)))) {
                continue;
            }

            // $empty: 出力を保留している空値の個数。後段で値ありが出たときに合流させる。
            // $buf  : このフィールド分のトークン列バッファ。
            $empty  = 0;
            $buf    = [];

            for ($i = 0; $i < $cnt; $i++) {
                $value      = isset($aryValue[$i]) ? $aryValue[$i] : '';
                $connector  = isset($aryConnector[$i]) ? $aryConnector[$i] : '';
                $operator   = isset($aryOperator[$i]) ? $aryOperator[$i] : '';

                switch ($operator) {
                    case 'eq':
                    case 'neq':
                    case 'lt':
                    case 'lte':
                    case 'gt':
                    case 'gte':
                    case 'lk':
                    case 'nlk':
                    case 're':
                    case 'nre':
                        if ('' !== $value) {
                            // 保留していた空値を先にフラッシュ（連続スラッシュで往復再現できるように）
                            for ($j = 0; $j < $empty; $j++) {
                                $buf[]  = '';
                            }
                            $empty  = 0;

                            if ('or' == $connector) {
                                // eq は or との組合せのときだけ省略する（parse 側の eq→or 強制と対）
                                if ('eq' != $operator) {
                                    $buf[]  = 'or';
                                    $buf[]  = $operator;
                                }
                                $buf[]  = $value;
                            } else {
                                // connector='and' は出さない。operator と value のみ。
                                $buf[]  = $operator;
                                $buf[]  = $value;
                            }
                            break;
                        } else {
                            // 値が空文字: いったん保留に積む。後続に値ありフィルターが
                            // 来れば '' トークンとしてフラッシュ、来なければ捨てる。
                            $empty++;
                        }
                        break;
                    case 'em':
                    case 'nem':
                        // em/nem は値なしでも必ず出力する。保留空値を先にフラッシュしてから
                        // connector='or' のときだけ 'or' を前置し、最後に演算子トークンを置く。
                        for ($j = 0; $j < $empty; $j++) {
                            $buf[]  = '';
                        }
                        $empty  = 0;
                        if ('or' == $connector) {
                            $buf[]  = 'or';
                        }
                        $buf[]  = $operator;
                        break;
                    default:
                        // 未知の演算子: 何も書かず空トークンとして残す（防御的）
                        $buf[]  = '';
                }
            }

            // フィールド分のトークン列が 1 つ以上できたら、separator + フィールド名を頭に付けて aryQuery に連結。
            // 先頭フィールドにも separator が必ず付くが、ループ外で先頭分を後から削る。
            $aryTmp = [];
            if (!empty($buf)) {
                if ($separator === 'or') {
                    $aryTmp[] = '_or_';
                } else {
                    $aryTmp[] = '_and_';
                }
                $aryTmp[] = $fd;
                foreach ($buf as $token) {
                    $aryTmp[] = $token;
                }

                $buf    = [];
                $aryQuery = array_merge($aryQuery, $aryTmp);
            }
        }
        // 先頭フィールドの separator は不要（前段がないため）。`_or_` / `_and_` / `and` のいずれかなら除去。
        if (!empty($aryQuery) && in_array($aryQuery[0], ['_or_', '_and_', 'and'], true)) {
            array_shift($aryQuery);
        }

        return join('/', $aryQuery);
    }

    /**
     * 指定したフィールドが em 演算子を持つかどうかを判定する
     * @param string $fd
     * @return bool
     */
    public function hasEmptyOperator(string $fd): bool
    {
        return in_array('em', $this->_aryOperator[$fd], true);
    }

    /**
     * @inheritDoc
     */
    public function cloneWith(array $fieldNames)
    {
        $field = parent::cloneWith($fieldNames);
        foreach ($fieldNames as $fieldName) {
            $operators = $this->_aryOperator[$fieldName] ?? [];
            $connectors = $this->_aryConnector[$fieldName] ?? [];
            $separator = $this->_arySeparator[$fieldName] ?? 'and';
            $field->setOperator($fieldName);
            foreach ($operators as $operator) {
                $field->addOperator($fieldName, $operator);
            }
            $field->setConnector($fieldName);
            foreach ($connectors as $connector) {
                $field->addConnector($fieldName, $connector);
            }
            $field->addSeparator($fieldName, $separator);
        }
        return $field;
    }

    /**
     * POST データ（extract('field') の返り値など）から Field_Search を生成するファクトリーメソッド。
     * field[field][]=key, field[key][]=value, field[key@operator][]=op,
     * field[key@connector][]=con, field[key@separator]=sep の形式に対応。
     *
     * @param Field $post
     * @return self
     */
    public static function fromPost(Field $post): self
    {
        /** @var list<'eq'|'neq'|'gt'|'gte'|'lt'|'lte'|'lk'|'nlk'|'re'|'nre'|'em'|'nem'> */
        static $allowedOperators = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'lk', 'nlk', 're', 'nre', 'em', 'nem'];

        $fieldSearch = new self();

        $keys = $post->getArray('field');
        foreach ($keys as $rawKey) {
            $key = trim((string) $rawKey);
            if ($key === '') {
                continue;
            }

            $aryValue     = $post->getArray($key);
            $aryConnector = $post->getArray($key . '@connector');
            $aryOperator  = $post->getArray($key . '@operator');
            $separator    = $post->get($key . '@separator', 'and') === 'or' ? 'or' : 'and';

            $cnt = max(count($aryValue), count($aryConnector), count($aryOperator));

            // 値が一件もない場合はフィールドだけ登録して終了（legacyBuildFieldSearch と同一挙動）
            if ($cnt <= 0) {
                $fieldSearch->set($key);
                $fieldSearch->setConnector($key);
                $fieldSearch->setOperator($key);
                continue;
            }

            $defaultConnector = (count($aryConnector) === 0 && count($aryOperator) === 0) ? 'or' : 'and';
            if (count($aryConnector) > 0) {
                $defaultConnector = $aryConnector[0];
            }
            $defaultOperator = 'eq';
            if (count($aryOperator) > 0 && in_array($aryOperator[0], $allowedOperators, true)) {
                $defaultOperator = $aryOperator[0];
            }

            // 値を先に収集し、空値かつ em/nem 以外の行を除外する。
            // Filter._field() の else 分岐が空 WHERE を外側クエリに直接追記するバグを防ぐため。
            $rows = [];
            for ($i = 0; $i < $cnt; $i++) {
                $val         = $aryValue[$i] ?? '';
                $connector   = $aryConnector[$i] ?? $defaultConnector;
                $operatorRaw = $aryOperator[$i] ?? $defaultOperator;
                $operator    = in_array($operatorRaw, $allowedOperators, true) ? $operatorRaw : 'eq';

                if ($val === '' && $operator !== 'em' && $operator !== 'nem') {
                    continue;
                }
                $rows[] = [$val, $connector, $operator];
            }

            // 有効な行がゼロ（全値が空文字の eq/neq 等）なら field ごとスキップ
            if ($rows === []) {
                continue;
            }

            $fieldSearch->set($key);
            $fieldSearch->setConnector($key);
            $fieldSearch->setOperator($key);
            $fieldSearch->addSeparator($key, $separator);

            foreach ($rows as [$val, $connector, $operator]) {
                $fieldSearch->add($key, $val);
                $fieldSearch->addConnector($key, $connector);
                $fieldSearch->addOperator($key, $operator);
            }
        }

        return $fieldSearch;
    }

    /**
     * フィールド検索条件を人間が読みやすい文字列（スナップショット）に変換する。
     *
     * 出力例（各行が1つのフィールドキーに対応する）:
     *   pref: 東京都 OR 大阪府
     *   gender: 男性
     *   age: >= 18 AND < 65
     *
     * ─ 同一フィールド内の複数値（コネクター） ─
     * _aryConnector[$key] に従い AND / OR で連結する（省略時は AND）。
     * 上記の例では "pref" の値 "東京都" と "大阪府" は OR で結合されている。
     *
     * ─ 異なるフィールドキー間（セパレーター） ─
     * _arySeparator[$key] に AND / OR が設定される。2行目以降の行頭に
     * セパレーターをプレフィックスとして付与する（最初のフィールドは先行条件がないため省略）。
     *
     *   pref: 東京都 OR 大阪府
     *   AND gender: 男性
     *   OR age: >= 18 AND < 65
     *
     * セパレーターには以下の制約がある:
     * - フィールドの並び順は AND 条件を OR 条件より前にしなければならない（AND → OR の順序のみ許可）
     * - em（空）演算子は OR セパレーターのフィールドでは使用できない
     *
     * 演算子の出力形式:
     * - eq（等値一致）はラベルなしで値のみ出力する
     * - em / nem は値の代わりに [空] / [非空] を出力する
     * - その他の演算子は値の前にラベル（"!= ", "> ", "<= " 等）を付与する
     *
     * @return string 改行区切りの条件文字列（条件がない場合は空文字列）
     */
    public function toSnapshot(): string
    {
        $opLabels = [
            'neq' => '!= ', 'gt'  => '> ',  'gte' => '>= ',
            'lt'  => '< ',  'lte' => '<= ', 'lk'  => '[lk] ',
            'nlk' => '[nlk] ', 're' => '[re] ', 'nre' => '[nre] ',
        ];
        $lines   = [];
        $isFirst = true;
        foreach ($this->listFields() as $key) {
            $values     = $this->getArray($key);
            $operators  = $this->_aryOperator[$key] ?? [];
            $connectors = $this->_aryConnector[$key] ?? [];
            $parts      = [];
            // em/nem は空文字の値を持つため getArray が除去してしまう。
            // operators の件数を基準にループし、値は存在しなければ '' で補う。
            for ($i = 0, $count = count($operators); $i < $count; $i++) {
                $value = $values[$i] ?? '';
                $op    = $operators[$i] ?? 'eq';
                $con   = strtoupper($connectors[$i] ?? 'and');
                if ($op === 'em') {
                    $token = '[空]';
                } elseif ($op === 'nem') {
                    $token = '[非空]';
                } else {
                    $token = ($opLabels[$op] ?? '') . $value;
                }
                $parts[] = ($i > 0 ? $con . ' ' : '') . $token;
            }
            if ($parts !== []) {
                $separator = strtoupper($this->_arySeparator[$key] ?? 'and');
                $line      = $key . ': ' . implode(' ', $parts);
                $lines[]   = $isFirst ? $line : $separator . ' ' . $line;
                $isFirst   = false;
            }
        }
        return implode("\n", $lines);
    }
}

class Field_Validation extends Field
{
    /**
     * @var array<string, array<string, array<int, bool>>>
     */
    public $_aryV = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public $_aryMethod = [];

    /**
     * @var array<string, string>
     */
    public $_aryGroup = [];

    /**
     * @inheritDoc
     */
    public function overload($Field, $isDeep = false)
    {
        if (!is_null($Field)) {
            parent::overload($Field, $isDeep);
            if ($Field instanceof Field_Validation) {
                $this->_aryV        = $Field->_aryV;
                $this->_aryMethod   = $Field->_aryMethod;
                $this->_aryGroup    = $Field->_aryGroup;
            }
        }
    }

    /**
     * @static
     * @param string $key
     * @param null|Field $Field
     * @return Field_Validation
     */
    public static function & singleton($key, $Field = null)
    {
        static $aryField  = [];

        if (!isset($aryField[$key]) || $Field !== null) {
            $aryField[$key] = new Field_Validation($Field);
        }

        return $aryField[$key];
    }

    /**
     * $validatorがtrueの場合は、バリデーションの値を含めたフィールド名を返す
     *
     * @param bool $validator
     * @return string[]
     */
    public function listFields($validator = false)
    {
        $aryFd  = parent::listFields();
        if (!!$validator) {
            $aryFd = array_unique(array_merge($aryFd, array_keys($this->_aryV)));
        }
        return $aryFd;
    }

    /**
     * @inheritDoc
     */
    public function delete($fd)
    {
        if (!is_string($fd)) {
            return false;
        }
        parent::delete($fd);
        unset($this->_aryV[$fd]);
        unset($this->_aryMethod[$fd]);
        unset($this->_aryGroup[$fd]);

        return true;
    }

    /**
     * バリデーションメソッドを設定する
     * @param string|null $fd
     * @param string|null $name
     * @param mixed $arg
     */
    public function setMethod($fd = null, $name = null, $arg = null)
    {
        if (is_null($fd) || !is_string($fd)) {
            $this->_aryMethod = [];
        } elseif (is_null($name)) {
            $this->_aryMethod[$fd]    = null;
        } else {
            $this->_aryMethod[$fd][$name] = $arg;
        }
    }

    /**
     * 指定したフィールド名のフィールドをフィールドグループに属するフィールドとして設定する
     * @param string|null $fd
     * @param string|null $group
     * @return void
     */
    public function setGroup($fd = null, $group = null)
    {
        if (is_null($fd) || !is_string($fd)) {
            $this->_aryGroup = [];
        } elseif (is_null($group)) {
            $this->_aryGroup[$fd] = null;
        } else {
            $this->_aryGroup[$fd] = $group;
        }
    }

    /**
     * 指定したフィールド名のフィールドがフィールドグループに属するフィールドかどうかを判定する
     * @param string $fd
     * @return bool
     */
    public function isGroup($fd)
    {
        if (isset($this->_aryGroup[$fd]) && !!$this->_aryGroup[$fd]) {
            return true;
        }
        return false;
    }

    /**
     * 指定したフィールド名のフィールドに対するバリデーションメソッドを配列で取得する
     * @param string $fd
     * @return string[]
     */
    public function listMethods($fd)
    {
        if (!is_string($fd)) {
            return [];
        }
        if (!isset($this->_aryV[$fd])) {
            return [];
        }
        return array_keys($this->_aryV[$fd]);
    }

    /**
     * ailas for listMethods
     * @param string $fd
     * @return string[]
     */
    public function getMethods($fd)
    {
        return $this->listMethods($fd);
    }

    /**
     * 指定したフィールド名のフィールドに対するバリデーションメソッドのバリデーション結果を設定する
     * @param string $fd
     * @param string $method
     * @param bool $validation
     * @param int $i
     * @return bool
     */
    public function setValidator($fd, $method = null, $validation = null, $i = 0)
    {
        if (!is_string($fd)) { // @phpstan-ignore-line
            return false;
        }
        $this->_aryV[$fd][$method][$i]  = $validation;
        return true;
    }

    /**
     * バリデーションをリセットする
     * @param bool $isDeep 非推奨の引数です。使用しないでください。
     * @return true
     */
    public function reset($isDeep = false)
    {
        $this->_aryV        = [];
        $this->_aryMethod   = [];
        $this->_aryGroup    = [];
        foreach ($this->listChildren() as $child) {
            $Child  = $this->getChild($child);
            $Child->reset($isDeep);
        }
        return true;
    }

    /**
     * 指定したフィールド名のフィールドの指定したバリデーションメソッドによる検証結果を判定する
     * $fdを指定しない場合は、すべてのフィールドに対するバリデーションメソッドによる検証結果を判定する
     * @param string|null $fd
     * @param string|null $method
     * @param int|null $i
     * @return bool
     */
    public function isValid($fd = null, $method = null, $i = null)
    {
        if (is_null($fd)) {
            // フィールド名が指定されていない場合は、すべてのフィールドに対するバリデーションメソッドによる検証結果を判定する
            foreach ($this->_aryV as $fdata) {
                foreach ($fdata as $vdata) {
                    foreach ($vdata as $validation) {
                        if ($validation === false) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }

        if (is_null($method) && !is_null($i)) {
            // メソッド名と指定されていない & インデックスが指定されている場合は、指定したフィールドの指定インデックスに対するすべてのバリデーションメソッドによる検証結果を判定する
            if (isset($this->_aryV[$fd])) {
                foreach ($this->_aryV[$fd] as $vdata) {
                    if (isset($vdata[$i])) {
                        if ($vdata[$i] === false) {
                            return false;
                        };
                    }
                }
            }
            return true;
        }

        if (is_null($method)) {
            // メソッド名が指定されていない場合は、指定したフィールドに対するすべてのバリデーションメソッドによる検証結果を判定する
            if (isset($this->_aryV[$fd])) {
                foreach ($this->_aryV[$fd] as $vdata) {
                    foreach ($vdata as $validation) {
                        if ($validation === false) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }

        if (is_null($i)) {
            // インデックスが指定されていない場合は、指定したフィールドに対する指定したバリデーションメソッドによる検証結果を判定する
            if (isset($this->_aryV[$fd][$method])) {
                foreach ($this->_aryV[$fd][$method] as $validation) {
                    if ($validation === false) {
                        return false;
                    }
                }
            }
            return true;
        }

        if (isset($this->_aryV[$fd][$method][$i])) {
            return $this->_aryV[$fd][$method][$i];
        }

        return true;
    }

    /**
     * 子フィールドも含めすべてのフィールドに対するバリデーションメソッドによる検証結果を判定する
     * @return bool
     */
    public function isValidAll()
    {
        $res = $this->isValid();
        foreach ($this->listChildren() as $child) {
            $Child  = $this->getChild($child);
            if ($Child instanceof Field_Validation) {
                if (!$Child->isValidAll()) {
                    return false;
                }
            }
        }

        return $res;
    }

    /**
     * すべてのフィールドに対して、バリデーションメソッドによる検証を実行する
     * @param \ACMS_Validator|null $V
     * @return true
     */
    public function validate($V = null)
    {
        $this->_aryV    = [];
        foreach ($this->_aryMethod as $fd => $method) {
            foreach ($method as $name => $arg) {
                if ($aryFd = $this->getArray($fd)) {
                    if (substr($name, 0, 4) == 'all_') {
                        $res = is_callable([$V, $name]) ? $V->$name($aryFd, $arg, $this) : !!$arg; // @phpstan-ignore-line
                        $this->setValidator($fd, $name, $res, 0);
                    } else {
                        foreach ($aryFd as $i => $val) {
                            $res = is_callable([$V, $name]) ? $V->$name($val, $arg, $this) : !!$arg; // @phpstan-ignore-line
                            $this->setValidator($fd, $name, $res, $i);
                        }
                    }
                } elseif (!$this->isGroup($fd)) {
                    $value = substr($name, 0, 4) === 'all_' ? [] : null;
                    $res = is_callable([$V, $name]) ? $V->$name($value, $arg, $this) : !!$arg; // @phpstan-ignore-line
                    $this->setValidator($fd, $name, $res, 0);
                }
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function &dig($scp = 'field')
    {
        $Field =& $this->getChild($scp);

        if (!($Field instanceof Field_Validation)) {
            return parent::dig($scp);
        }

        if ($aryFd = $this->getArray($scp, true)) {
            //-------
            // group
            foreach ($aryFd as $fd) {
                if (preg_match('/^@(.*)$/', $fd, $match) && $match[1]) {
                    $group = $match[1];
                    foreach ($this->getArray($fd) as $item) {
                        $this->setGroup($item, $group);
                        $Field->setGroup($item, $group);
                    }
                }
            }

            //--------
            // fields
            foreach ($aryFd as $fd) {
                //if ( !$this->isExists($fd) ) continue;
                $Field->setField($fd, $this->getArray($fd));
                $this->deleteField($fd);
            }

            //-----------
            // reference
            foreach ($aryFd as $fd) {
                if ('&' !== substr($Field->get($fd), 0, 1)) {
                    continue;
                }
                $_fd    = preg_replace('@^\s*&\s*|\s*;$@', '', $Field->get($fd));
                if ($Field->isNull($_fd)) {
                    continue;
                }
                $Field->setField($fd, $Field->get($_fd));
            }

            //-----------
            // validator
            $aryFdSearch    = $this->listFields();
            foreach ($aryFd as $fd) {
                if (!is_string($fd)) {
                    continue;
                }
                foreach ($aryFdSearch as $search) {
                    if (
                        preg_match(
                            '@^' . preg_quote($fd, '@') . '(?:\:v#|\:validator#)(.+)$@',
                            $search,
                            $match
                        )
                    ) {
                        $Field->setMethod($fd, $match[1], $this->get($match[0]));
                        $this->deleteField($match[0]);
                    }
                }
            }
            $Field->validate();
        }
        $this->deleteField($scp);

        return $Field;
    }

    /**
     * @inheritDoc
     */
    public function cloneWith(array $fieldNames)
    {
        $field = parent::cloneWith($fieldNames);
        foreach ($fieldNames as $fieldName) {
            $validators = $this->_aryV[$fieldName] ?? [];
            $methods = $this->_aryMethod[$fieldName] ?? [];
            $group = $this->_aryGroup[$fieldName] ?? '';
            if ($group !== '') {
                $field->setGroup($fieldName, $group);
            }
            foreach ($validators as $method => $validations) {
                foreach ($validations as $i => $validation) {
                    $field->setValidator($fieldName, $method, $validation, $i);
                }
            }
            $field->setMethod($fieldName);
            foreach ($methods as $method => $arg) {
                $field->setMethod($fieldName, $method, $arg);
            }
        }
        return $field;
    }
}
