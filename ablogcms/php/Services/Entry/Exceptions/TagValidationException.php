<?php

namespace Acms\Services\Entry\Exceptions;

use RuntimeException;

/**
 * タグのバリデーションエラーをまとめて保持する例外
 */
class TagValidationException extends RuntimeException
{
    /**
     * エラー情報の配列
     * [
     *   ['tag' => 'タグ名', 'index' => 0, 'type' => 'reserved', 'message' => '...'],
     *   ['tag' => 'タグ名', 'index' => 0, 'type' => 'invalid_format', 'message' => '...'],
     *   ...
     * ]
     *
     * @var array<int, array{tag: string, index: int, type: string, message: string}>
     */
    private array $errors;

    /**
     * @param array<int, array{tag: string, index: int, type: string, message: string}> $errors エラー情報の配列
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct($this->buildMessage());
    }

    /**
     * エラー情報を取得
     *
     * @return array<int, array{tag: string, index: int, type: string, message: string}>
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
        $tagMessages = [];
        foreach ($this->errors as $error) {
            $tag = $error['tag'];
            if (!isset($tagMessages[$tag])) {
                $tagMessages[$tag] = [];
            }
            $tagMessages[$tag][] = $error['message'];
        }
        foreach ($tagMessages as $tag => $msgs) {
            $messages[] = 'タグ「' . $tag . '」: ' . implode('、', $msgs);
        }
        return 'タグのバリデーションエラー: ' . implode(' | ', $messages);
    }
}
