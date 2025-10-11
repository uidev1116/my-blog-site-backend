<?php

namespace Acms\Modules\Get\V2\User;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\User\UserHelper;
use ACMS_RAM;
use RuntimeException;

class Field extends Base
{
    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'uid' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->uid) {
                throw new RuntimeException('Not found user id.');
            }
            $userHelper = new UserHelper($this->getBaseParams([]));
            if (!$userHelper->canAccessUser($this->uid)) {
                throw new RuntimeException('Permission denied.');
            }
            $user = ACMS_RAM::user($this->uid);
            $vars = [
                'uid' => (int) $user['user_id'],
                'code' => $user['user_code'],
                'status' => $user['user_status'],
                'name' => $user['user_name'],
                'email' => $user['user_mail'],
                'url' => $user['user_url'],
                'icon' => loadUserIcon($this->uid),
                'indexing' => $user['user_indexing'],
                'createdAt' => $user['user_generated_datetime'],
                'updatedAt' => $user['user_updated_datetime'],
            ];
            $vars['fields'] = $this->buildFieldTrait(loadUserField($this->uid));
            $vars['moduleFields'] = $this->buildModuleField();
            $vars['geo'] = null;
            if (config('geolocation_user_function') === 'on') {
                $geo = loadGeometry('uid', $this->uid);
                $vars['geo'] = $this->buildFieldTrait($geo);
            } else {
                $vars['geo'] = null;
            }
            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }
}
