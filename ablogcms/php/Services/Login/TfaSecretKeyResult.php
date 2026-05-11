<?php

declare(strict_types=1);

namespace Acms\Services\Login;

use Acms\Services\Login\Enums\TfaSecretKeyStatus;
use LogicException;

/**
 * 2段階認証の秘密鍵取得結果。
 *
 * 「鍵が取れた」「未登録」「復号失敗」の3状態を1つの値で表現する。
 * 成功時のみ秘密鍵の値を保持する。
 */
final class TfaSecretKeyResult
{
    private function __construct(
        public readonly TfaSecretKeyStatus $status,
        private readonly ?string $value,
    ) {
    }

    public static function success(string $value): self
    {
        return new self(TfaSecretKeyStatus::SUCCESS, $value);
    }

    public static function notRegistered(): self
    {
        return new self(TfaSecretKeyStatus::NOT_REGISTERED, null);
    }

    public static function decryptFailed(): self
    {
        return new self(TfaSecretKeyStatus::DECRYPT_FAILED, null);
    }

    public function isSuccess(): bool
    {
        return $this->status === TfaSecretKeyStatus::SUCCESS;
    }

    public function isNotRegistered(): bool
    {
        return $this->status === TfaSecretKeyStatus::NOT_REGISTERED;
    }

    public function isDecryptFailed(): bool
    {
        return $this->status === TfaSecretKeyStatus::DECRYPT_FAILED;
    }

    /**
     * 成功時のみ秘密鍵を返す。失敗状態で呼ぶと例外。
     */
    public function getValue(): string
    {
        if ($this->value === null) {
            throw new LogicException('Cannot get value from non-success TfaSecretKeyResult: ' . $this->status->value);
        }
        return $this->value;
    }

    /**
     * 成功なら秘密鍵、それ以外は null。
     */
    public function getValueOrNull(): ?string
    {
        return $this->value;
    }
}
