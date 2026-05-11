<?php

namespace Acms\Services\Entry\Exceptions;

use RuntimeException;

/**
 * サブカテゴリーのバリデーションエラーをまとめて保持する例外
 */
class SubCategoryValidationException extends RuntimeException
{
    /**
     * エラー情報の配列
     * [
     *   ['type' => 'limit_exceeded', 'message' => '...', 'limit' => 10, 'count' => 15],
     *   ...
     * ]
     *
     * @var array<int, array{type: string, message: string, limit?: int, count?: int}>
     */
    private array $errors;

    /**
     * @param array<int, array{type: string, message: string, limit?: int, count?: int}> $errors エラー情報の配列
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct($this->buildMessage());
    }

    /**
     * エラー情報を取得
     *
     * @return array<int, array{type: string, message: string, limit?: int, count?: int}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * エラーメッセージを構築
     *
     * @return string
     */
    private function buildMessage(): string
    {
        $messages = [];
        foreach ($this->errors as $error) {
            $messages[] = $error['message'];
        }
        return 'サブカテゴリーのバリデーションエラー: ' . implode(' | ', $messages);
    }
}
