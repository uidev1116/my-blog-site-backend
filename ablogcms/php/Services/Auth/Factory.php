<?php

namespace Acms\Services\Auth;

use Acms\Contracts\Factory as BaseFactory;
use App;
use Acms\Services\Facades\Login;

class Factory extends BaseFactory
{
    /**
     * Factory
     *
     * @return mixed
     */
    public function createInstance()
    {
        if ($this->isRoleAvailableUser()) {
            return App::make('auth.role');
        } elseif (Login::isLoggedIn() && config('subscriber_view_mode') === 'on') {
            $app = App::getInstance();
            assert($app instanceof \Acms\Application);
            $Q =& $app->getQueryParameter();
            if (empty($Q->get('admin')) && empty($Q->get('bid')) && !preg_match('/ajax\//', $Q->get('tpl'))) {
                return new SimulateSubscriber();
            }
        }
        return App::make('auth.general');
    }

    /**
     * ロールによる権限チェッックを行うユーザーか
     *
     * @param int $uid
     * @return bool
     */
    protected function isRoleAvailableUser($uid = SUID)
    {
        return isRoleAvailableUser($uid);
    }
}
