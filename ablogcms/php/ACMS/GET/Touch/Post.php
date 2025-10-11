<?php

class ACMS_GET_Touch_Post extends ACMS_GET
{
    public function get()
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->tpl;
        }

        return '';
    }
}
