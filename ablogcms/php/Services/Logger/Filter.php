<?php

namespace Acms\Services\Logger;

class Filter
{
    /**
     * 機密フィールドのリスト
     *
     * @var array
     */
    private array $sensitiveFields = [
        'password',
        'passwd',
        'pass',
    ];

    /**
     * マスク文字列
     *
     * @var string
     */
    private string $mask = '***MASKED***';

    /**
     * コンストラクタ
     *
     * @param array $additionalFields
     * @param string $mask
     */
    public function __construct(array $additionalFields = [], string $mask = '***MASKED***')
    {
        $this->sensitiveFields = array_merge($this->sensitiveFields, $additionalFields);
        $this->mask = $mask;
    }

    /**
     * 安全にフィルタリングされた配列を取得
     *
     * @param array $data
     * @return array
     */
    public function getSafeArray(array $data): array
    {
        return $this->filterArray($data);
    }

    /**
     * 配列を再帰的にフィルタリング
     *
     * @param array $data
     * @return array
     */
    protected function filterArray(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $filtered[$key] = match (true) {
                is_array($value) => $this->filterArray($value),
                $this->isSensitiveField($key) => $this->getMaskedValue(),
                default => $value
            };
        }
        return $filtered;
    }

    /**
     * 機密フィールドを一括設定
     *
     * @param array $fields
     * @return self
     */
    public function setSensitiveFields(array $fields): self
    {
        $this->sensitiveFields = $fields;
        return $this;
    }

    /**
     * マスク文字列を設定
     *
     * @param string $mask
     * @return self
     */
    public function setMask(string $mask): self
    {
        $this->mask = $mask;
        return $this;
    }

    /**
     * 設定されている機密フィールド一覧を取得
     *
     * @return array
     */
    public function getSensitiveFields(): array
    {
        return $this->sensitiveFields;
    }

    /**
     * フィールド名が機密情報に該当するかチェック（部分一致）
     *
     * @param string $fieldName
     * @return bool
     */
    private function isSensitiveField(string|int $fieldName): bool
    {
        if (is_int($fieldName)) {
            return false;
        }
        $fieldName = strtolower($fieldName);

        return array_any(
            $this->sensitiveFields,
            fn(string $sensitiveField): bool => str_contains($fieldName, strtolower($sensitiveField))
        );
    }

    /**
     * マスクされた値を取得
     *
     * @return string
     */
    private function getMaskedValue(): string
    {
        return $this->mask;
    }
}
