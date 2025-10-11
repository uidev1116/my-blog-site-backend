<?php

class ACMS_GET_Touch_Get extends ACMS_GET
{
    public function get()
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            return $this->tpl;
        }

        return '';
    }
}
