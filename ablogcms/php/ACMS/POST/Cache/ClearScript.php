<?php

class ACMS_POST_Cache_ClearScript extends ACMS_POST_Cache
{
    /**
     * validate
     */
    protected function validate()
    {
        try {
            $cron_key1 = LocalStorage::get('private/cronkey');
        } catch (\Exception $e) {
            return false;
        }

        $cron_key2 = $this->Post->get('ACMS_POST_Cache_ClearScript');

        if (
            1
            && !!$cron_key1
            && !!$cron_key2
            && trim($cron_key1) === trim($cron_key2)
        ) {
            return true;
        }
    }


    /**
     * run
     */
    function post()
    {
        if (!$this->validate()) {
            die403("Forbidden: Access is denied.\n");
        }
        ACMS_POST_Cache::clearPageCache();
        die('ok');
    }
}
