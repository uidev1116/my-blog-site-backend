<?php

class ACMS_POST_Update_Base extends ACMS_POST
{
    /**
     * 権限をチェック
     *
     * @return bool
     */
    protected function validatePermissions(): bool
    {
        if (!sessionWithAdministration()) {
            return false;
        }
        if ('update' <> ADMIN) {
            return false;
        }
        if (RBID !== BID) { // @phpstan-ignore-line
            return false;
        }
        if (SBID !== BID) { // @phpstan-ignore-line
            return false;
        }
        return true;
    }
}
