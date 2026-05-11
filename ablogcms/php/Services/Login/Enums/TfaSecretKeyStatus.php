<?php

declare(strict_types=1);

namespace Acms\Services\Login\Enums;

/**
 * 2段階認証の秘密鍵取得の状態
 */
enum TfaSecretKeyStatus: string
{
    case SUCCESS = 'success';
    case NOT_REGISTERED = 'not_registered';
    case DECRYPT_FAILED = 'decrypt_failed';
}
