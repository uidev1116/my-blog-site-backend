<?php

class ACMS_ValidatorBody
{
    /**
     * @param string|null $val
     * @return bool
     */
    public function required($val)
    {
        $tmp = preg_replace('/^[\s　]*(.*?)[\s　]*$/u', '\1', $val ?? '');
        return !empty($tmp) or ('0' === $tmp);
    }

    /**
     * @param string|null $val
     * @param string $arg 最小文字数
     * @return bool
     */
    public function minlength($val, $arg)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        // mb_strlenは改行コード（CR+LF）を2文字としてカウントするため、
        // 改行コードを1文字としてカウントするように置換する
        $string = str_replace("\r\n", " ", $val);
        return intval($arg) <= mb_strlen($string, 'UTF-8');
    }

    /**
     * @param string|null $val
     * @param string $arg 最大文字数
     * @return bool
     */
    public function maxlength($val, $arg)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        // mb_strlenは改行コード（CR+LF）を2文字としてカウントするため、
        // 改行コードを1文字としてカウントするように置換する
        $string = str_replace("\r\n", " ", $val);
        return intval($arg) >= mb_strlen($string, 'UTF-8');
    }

    /**
     * @param string|null $val
     * @param string $arg 最小値
     * @return bool
     */
    public function min($val, $arg)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        return intval($arg) <= intval($val);
    }

    /**
     * @param string|null $val
     * @param string $arg 最大値
     * @return bool
     */
    function max($val, $arg)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        return intval($arg) >= intval($val);
    }

    /**
     * @param string|null $val
     * @param string $regex 正規表現文字列 デリミタは@を使用
     * @return bool
     */
    public function regex($val, $regex)
    {
        if (empty($regex)) {
            return false;
        }
        if ('' === $val || null === $val) {
            return true;
        }

        //---------------
        // compatibility
        if ('@' !== substr($regex, 0, 1)) {
            $regex  = '@' . $regex . '@';
        }

        if (preg_match($regex, $val) === 1) {
            return true;
        }

        if (multiBytePregMatch($regex . 'u', $val) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param string|null $val
     * @param string $regexp 正規表現文字列 デリミタは@を使用
     */
    public function regexp($val, $regexp)
    {
        return $this->regex($val, $regexp);
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function digits($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        return is_numeric($val);
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function email($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        $ptn    = '/^(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|"[^\\\\\x80-\xff\n\015"]*(?:\\\\[^\x80-\xff][^\\\\\x80-\xff\n\015"]*)*")(?:\.(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|"[^\\\\\x80-\xff\n\015"]*(?:\\\\[^\x80-\xff][^\\\\\x80-\xff\n\015"]*)*"))*@(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\\\x80-\xff\n\015\[\]]|\\\\[^\x80-\xff])*\])(?:\.(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\\\x80-\xff\n\015\[\]]|\\\\[^\x80-\xff])*\]))*$/';
        return preg_match($ptn, $val) === 1;
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function url($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        return multiBytePregMatch('@^(https?|ftp)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$@u', $val) === 1;
    }

    /**
     * @param string|null $val
     * @param string $name 比較対象のフィールド名
     * @param Field $Field
     * @return bool
     */
    public function equalTo($val, $name, $Field)
    {
        return $val == $Field->get($name);
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function dates($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        $ptn    = '@^[sS]{1,2}(\d{2})\W{1}\d{1,2}\W{1}\d{0,2}$|^[hH]{1}(\d{1,2})\W{1}\d{1,2}\W{1}\d{0,2}$|^\d{1,2}$|^\d{1,2}\W{1}\d{1,2}$|^\d{2,4}\W{1}\d{1,2}\W{1}\d{0,2}$|^\d{4}\d{2}\d{2}$@';
        return preg_match($ptn, $val) === 1;
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function times($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        $ptn    = '@^\d{1,2}$|^\d{1,2}\W{1}\d{1,2}$|^\d{1,2}\W{1}\d{1,2}\W{1}\d{1,2}$|^\d{2}\d{2}\d{2}$@';
        return preg_match($ptn, $val) === 1;
    }

    /**
     * @param string|null $val
     * @param array $choice 選択肢
     * @return bool
     */
    public function in($val, $choice)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        if (!is_array($choice)) {
            return false;
        }
        return in_array($val, $choice, true);
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function katakana($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        if (multiBytePregMatch("/^[ァ-ヾー]+$/u", $val)) {
            return true;
        }
        return false;
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function hiragana($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        if (multiBytePregMatch("/^[ぁ-ゞー]+$/u", $val)) {
            return true;
        }
        return false;
    }

    /**
     * @param array $ary
     * @param string $cnt
     * @return bool
     */
    public function all_justChecked($ary, $cnt)
    {
        return intval($cnt) == count($ary);
    }

    /**
     * @param array $ary
     * @param string $min
     * @return bool
     */
    public function all_minChecked($ary, $min)
    {
        return intval($min) <= count($ary);
    }

    /**
     * @param array $ary
     * @param string $max
     * @return bool
     */
    public function all_maxChecked($ary, $max)
    {
        return intval($max) >= count($ary);
    }

    /**
     * @param array $ary
     * @return bool
     */
    public function all_unique($ary)
    {
        if (!is_array($ary)) {
            return true;
        }
        $_ary = array_unique($ary);
        return count($ary) === count($_ary);
    }

    /**
     * @param string|null $val
     * @return bool
     */
    public function password($val)
    {
        if ('' === $val || null === $val) {
            return true;
        }
        $min = Config::get('password_validator_min', 0);
        $max = Config::get('password_validator_max', 9999);
        $uppercase = Config::get('password_validator_uppercase', 'off');
        $lowercase = Config::get('password_validator_lowercase', 'off');
        $digits = Config::get('password_validator_digits', 'off');
        $symbols = Config::get('password_validator_symbols', 'off');
        $type3 = Config::get('password_validator_3type', 'off');
        $blacklist = Config::get('password_validator_blacklist', '');
        $blacklist = preg_split("/[,\s\n]/", $blacklist);

        if (!preg_match('/[!-~]*/', $val)) {
            return false; // 不正な文字
        }
        if (strlen($val) < intval($min)) {
            return false;
        }
        if (strlen($val) > intval($max)) {
            return false;
        }
        $uppercase_check = preg_match('/[A-Z]/', $val);
        if ($uppercase === 'on' && !$uppercase_check) {
            return false;
        }
        $lowercase_check = preg_match('/[a-z]/', $val);
        if ($lowercase === 'on' && !$lowercase_check) {
            return false;
        }
        $digits_check = preg_match('/[0-9]/', $val);
        if ($digits === 'on' && !$digits_check) {
            return false;
        }
        $symbols_check = preg_match('/[!-\/:-@\[-`{-~]/', $val);
        if ($symbols === 'on' && !$symbols_check) {
            return false;
        }
        if ($type3 === 'on') {
            $count = 0;
            if ($uppercase_check) {
                $count++;
            }
            if ($lowercase_check) {
                $count++;
            }
            if ($digits_check) {
                $count++;
            }
            if ($symbols_check) {
                $count++;
            }
            if ($count < 3) {
                return false;
            }
        }
        if (is_array($blacklist) && count($blacklist) > 0) {
            foreach ($blacklist as $word) {
                if (empty($word)) {
                    continue;
                }
                if (strpos($val, $word) !== false) {
                    return false;
                }
            }
        }
        return true;
    }
}
