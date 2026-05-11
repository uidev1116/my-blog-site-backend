<?php

declare(strict_types=1);

namespace Acms\Services\Login\Enums;

/**
 * 2段階認証の認証結果
 */
enum TfaAuthResult: string
{
    case SUCCESS = 'success';
    case INVALID_CODE = 'invalid_code';
    case DECRYPT_FAILED = 'decrypt_failed';
    case NOT_REGISTERED = 'not_registered';
}
