<?php

class ACMS_POST_Fix_Confirm extends ACMS_POST
{
    function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        $Fix = $this->extract('fix', new ACMS_Validator());

        return $this->Post;
    }
}
