<?php

namespace Acms\Services\Webhook\Contracts;

use ACMS_RAM;
use Acms\Services\Facades\Login;

abstract class Payload
{
    protected function basicPayload($type, $events, $contents, $url = null)
    {
        $payload = [
            'webhook_id' => 0,
            'webhook_name' => '',
            'type' => $type,
            'event' => implode(',', $events),
            'actor' => null,
            'url' => $url,
            'contents' => $contents,
        ];
        if (Login::isLoggedIn()) {
            /** @var int|null $sessionUserId */
            $sessionUserId = SUID;
            assert(is_int($sessionUserId)); // ログインしていることが保証されている
            $payload['actor'] = [
                'uid' => $sessionUserId,
                'name' => ACMS_RAM::userName($sessionUserId),
            ];
        }
        return $payload;
    }
}
