<?php

class ACMS_POST_Fix_Replacement_Confirm extends ACMS_POST_Fix
{
    public function post()
    {
        $field = $this->extract('fix');
        if ($this->validate($field)) {
            $this->Post->set('step', 'confirm');
        }
        return $this->Post;
    }
}
