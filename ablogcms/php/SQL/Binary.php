<?php

class SQL_Binary
{
    public $_value = null;

    function __construct($val = null)
    {
        $this->set($val);
    }

    function set($val)
    {
        $this->_value   = $val;
        return true;
    }

    function get($dsn = null)
    {
        return $this->_value;
    }
}
