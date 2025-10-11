<?php

class SQL_Field extends SQL
{
    /**
     * @var SQL_Field|string|int|null
     */
    public $_field = null;

    /**
     * @var string|null
     */
    public $_scope = null;

    /**
     * @var boolean
     */
    protected $_quote = true;

    /**
     * @param SQL_Field|string|int|null $fd
     * @return true
     */
    public function setField($fd)
    {
        $this->_field = $fd;
        return true;
    }

    /**
     * @param string|null $scp
     * @return true
     */
    public function setScope($scp)
    {
        $this->_scope = $scp;
        return true;
    }

    /**
     * @param bool $quote
     */
    public function setQuote(bool $quote): void
    {
        $this->_quote = $quote;
    }

    /**
     * @return SQL_Field|string|int|null
     */
    public function getField()
    {
        return $this->_field;
    }

    /**
     * @return string|null
     */
    public function getScope()
    {
        return $this->_scope;
    }

    /**
     * @return bool
     */
    public function getQuote(): bool
    {
        return $this->_quote;
    }

    /**
     * @param Dsn|null $dsn
     * @return string|int|false
     */
    protected function _field($dsn = null)
    {
        if (!$this->_field) {
            return false;
        }
        if ($this->_field instanceof SQL_Field) {
            return $this->_field->getSQL();
        }
        if (is_string($this->_field) && trim($this->_field) === '*') {
            return $this->_scope ? self::quoteKey($this->_scope) . '.*' : '*';
        }
        if ($this->_quote && is_string($this->_field)) {
            return $this->_scope ? self::quoteKey($this->_scope) . '.' . self::quoteKey($this->_field) :  self::quoteKey($this->_field);
        }
        return $this->_field;
    }

    /**
     * @inharitDoc
     */
    public function get($dsn = null)
    {
        return [
            'sql' => (string) $this->_field($dsn),
            'params' => [],
        ];
    }
}
