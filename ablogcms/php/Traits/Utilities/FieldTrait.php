<?php

namespace Acms\Traits\Utilities;

use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Common;
use Field;
use Field_Validation;

trait FieldTrait
{
    /**
     * フィールドを連想配列として組み立て
     *
     * @param Field|Field_Validation $field
     * @param string|null $scp
     * @return array|null
     */
    protected function buildFieldTrait($field, ?string $scp = null): ?array
    {
        $builtData = [];
        TemplateHelper::injectMediaField($field);
        TemplateHelper::injectRichEditorField($field);
        TemplateHelper::injectBlockEditorField($field, true);
        $fieldKeys = $field instanceof Field_Validation ? $field->listFields(true) : $field->listFields();

        $groupKeys = $this->extractGroupFieldKeysTrait($field, $fieldKeys); // グループフィールドのキーを抜き出す
        $fieldKeys = $this->extractNonGroupFieldKeysTrait($fieldKeys, $groupKeys); // グループフィールドのキーを除いたキーを取得
        $formattedFieldKeys = $this->formatFieldKeysTrait($fieldKeys); // フィールドキー配列を整形

        // 基本カスタムフィールドを組み立て
        if (isset($formattedFieldKeys['items'])) {
            $builtData = $this->buildBasicFieldTrait($formattedFieldKeys['items'], $field, $builtData);
        }
        // 複数項目をもつカスタムフィールドを組み立て
        if (isset($formattedFieldKeys['groups'])) {
            $builtData = $this->buildMultiFieldTrait($formattedFieldKeys['groups'], $field, $builtData);
        }
        foreach ($groupKeys as $groupName => $keys) {
            $formattedGroupFieldKeys = $this->formatFieldKeysTrait($keys); // グループフィールドキー配列を整形
            $builtGroupData = [];
            // 基本カスタムフィールドグループを組み立て
            if (isset($formattedGroupFieldKeys['items'])) {
                $builtGroupData = $this->buildBasicFieldGroupTrait($formattedGroupFieldKeys['items'], $field, $builtGroupData);
            }
            // // 複数項目をもつカスタムフィールドグループを組み立て
            if (isset($formattedGroupFieldKeys['groups'])) {
                $builtGroupData = $this->buildMultiFieldGroupTrait($formattedGroupFieldKeys['groups'], $field, $builtGroupData);
            }
            $builtData[$groupName] = $builtGroupData;
        }
        if (!isApiBuild()) {
            if (!is_null($scp)) {
                $builtData[($scp ? "{$scp}:" : '') . 'takeover'] = acmsSerialize($field);
            }
        }
        foreach ($field->listChildren() as $child) {
            $builtData += $this->buildFieldTrait($field->getChild($child), $child);
        }
        return $builtData ? $builtData : null;
    }

    /**
     * 基本カスタムフィールドを組み立て
     *
     * @param array $keys
     * @param Field|Field_Validation $field
     * @param array $initial
     * @return array
     */
    protected function buildBasicFieldTrait(array $keys, $field, array $initial): array
    {
        return array_reduce($keys, function ($carry, $key) use ($field) {
            $data = $this->autoConvertTypeTrait($field->getArray($key));
            $value = $data[0] ?? null;
            $carry[$key]['value'] = $value;
            $carry[$key]['array'] = $data;
            if ($field instanceof Field_Validation && !isApiBuild()) {
                $carry[$key]['invalid'] = !$field->isValid($key);
                $aryMethod = $field->getMethods($key);
                foreach ($aryMethod as $method) {
                    $carry[$key]['v'][$method] = !$field->isValid($key, $method);
                }
            }
            return $carry;
        }, $initial);
    }

    /**
     * 複数項目をもつカスタムフィールドを組み立て
     *
     * @param array $items
     * @param Field|Field_Validation $field
     * @param array $initial
     * @return array
     */
    protected function buildMultiFieldTrait(array $items, $field, array $initial): array
    {
        foreach ($items as $baseName => $keys) {
            unset($initial[$baseName]);
            $initial = array_reduce($keys, function ($carry, $key) use ($field, $baseName) {
                $data = $this->autoConvertTypeTrait($field->getArray($baseName . '@' . $key));
                $value = $data[0] ?? null;
                if ($key === 'path' && $value && !$field->isExists("{$baseName}@media")) {
                    $value = Common::resolveUrl($value, ARCHIVES_DIR);
                } elseif ($key === 'html' && $value) {
                    $value = isApiBuildOrV2Module() ? Common::convertAssetUrlsToAbsolute($value, BASE_URL) : $value;
                }
                $carry[$baseName]['value'][$key] = $value;
                if (!$data) {
                    $carry[$baseName]['array'][0][$key] = null;
                } else {
                    foreach ($data as $i => $val) {
                        if ($key === 'path' && $val && !$field->isExists("{$baseName}@media")) {
                            $val = Common::resolveUrl($val, ARCHIVES_DIR);
                        } elseif ($key === 'html' && $val) {
                            $val = isApiBuildOrV2Module() ? Common::convertAssetUrlsToAbsolute($val, BASE_URL) : $val;
                        }
                        $carry[$baseName]['array'][$i][$key] = $val;
                    }
                }
                if ($field instanceof Field_Validation && !isApiBuild()) {
                    $carry[$baseName]['invalid'] = !$field->isValid($baseName);
                    $aryMethod = $field->getMethods($baseName);
                    foreach ($aryMethod as $method) {
                        $carry[$baseName]['v'][$method] = !$field->isValid($key, $method);
                    }
                }
                return $carry;
            }, $initial);
        }
        return $initial;
    }

    /**
     * 基本カスタムフィールドグループを組み立て
     *
     * @param array $keys
     * @param Field|Field_Validation $field
     * @param array $initial
     * @return array
     */
    protected function buildBasicFieldGroupTrait(array $keys, $field, array $initial): array
    {
        return array_reduce($keys, function ($carry, $key) use ($field) {
            $data = $this->autoConvertTypeTrait($field->getArray($key));
            foreach ($data as $i => $val) {
                $carry[$i][$key]['value'] = $val;
                if ($field instanceof Field_Validation && !isApiBuild()) {
                    $carry[$i][$key]['invalid'] = !$field->isValid($key, null, $i);
                    $aryMethod = $field->getMethods($key);
                    foreach ($aryMethod as $method) {
                        $carry[$i][$key]['v'][$method] = !$field->isValid($key, $method, $i);
                    }
                }
            }
            return $carry;
        }, $initial);
    }

    /**
     * 複数項目をもつカスタムフィールドグループを組み立て
     *
     * @param array $items
     * @param Field|Field_Validation $field
     * @param array $initial
     * @return array
     */
    protected function buildMultiFieldGroupTrait(array $items, $field, array $initial): array
    {
        foreach ($items as $baseName => $keys) {
            $initial = array_reduce($keys, function ($carry, $key) use ($field, $baseName) {
                $keys = array_keys($field->getArray($baseName . '@' . $key));
                foreach ($keys as $i) {
                    unset($carry[$i][$baseName]);
                }
                return $carry;
            }, $initial);
            $initial = array_reduce($keys, function ($carry, $key) use ($field, $baseName) {
                $data = $this->autoConvertTypeTrait($field->getArray($baseName . '@' . $key));
                foreach ($data as $i => $val) {
                    if ($key === 'path' && $val && !$field->isExists("{$baseName}@media")) {
                        $val = Common::resolveUrl($val, ARCHIVES_DIR);
                    } elseif ($key === 'html' && $val) {
                        $val = isApiBuildOrV2Module() ? Common::convertAssetUrlsToAbsolute($val, BASE_URL) : $val;
                    }
                    $carry[$i][$baseName][$key] = $val;
                    if ($field instanceof Field_Validation && !isApiBuild()) {
                        $carry[$i][$baseName]['invalid'] = !$field->isValid($baseName, null, $i);
                        $aryMethod = $field->getMethods($key);
                        foreach ($aryMethod as $method) {
                            $carry[$i][$baseName]['v'][$method] = !$field->isValid($baseName, $method, $i);
                        }
                    }
                }
                return $carry;
            }, $initial);
        }
        return $initial;
    }

    /**
     * グループフィールドのキーを抜き出す
     *
     * @param Field|Field_Validation $field
     * @param array $fieldKeys
     * @return array
     */
    protected function extractGroupFieldKeysTrait($field, array $fieldKeys): array
    {
        $groups = [];
        foreach ($fieldKeys as $key) {
            if (preg_match('/^@(.*)$/', $key, $match)) {
                $groupName = $match[1];
                $items = $field->getArray($key);
                $groups[$groupName] = array_reduce($fieldKeys, function ($carry, $item) use ($items) {
                    if (strpos($item, '@') !== false) {
                        list($mainKey, $subKey) = explode('@', $item);
                        if ($mainKey && $subKey && in_array($mainKey, $items, true)) {
                            $carry[] = $item;
                        }
                    }
                    return $carry;
                }, $items);
            }
        }
        return $groups;
    }

    /**
     * グループフィールドのキーを除いてキーを取得
     *
     * @param array $fieldKeys
     * @param array $groupKeys
     * @return array
     */
    protected function extractNonGroupFieldKeysTrait(array $fieldKeys, array $groupKeys): array
    {
        $allGroupKeys = array_reduce($groupKeys, function ($carry, $items) {
            $carry += $items;
            return $carry;
        }, []);

        return array_reduce($fieldKeys, function ($carry, $key) use ($groupKeys, $allGroupKeys) {
            if (isset($groupKeys[substr($key, 1)]) || in_array($key, $allGroupKeys, true)) {
                return $carry;
            }
            $carry[] = $key;
            return $carry;
        }, []);
    }

    /**
     * フィールドキー配列を整形
     *
     * @param array $fieldKeys
     * @return array
     */
    protected function formatFieldKeysTrait(array $fieldKeys): array
    {
        return array_reduce($fieldKeys, function ($carry, $item) {
            if (strpos($item, '@') !== false) {
                list($mainKey, $subKey) = explode('@', $item);
                if ($mainKey && $subKey) {
                    $carry['groups'][$mainKey][] = $subKey;
                }
            } else {
                $carry['items'][] = $item;
            }
            return $carry;
        }, []);
    }

    /**
     * 自動で型変換
     *
     * @param array $values
     * @return array
     */
    protected function autoConvertTypeTrait(array $values): array
    {
        $data = [];
        foreach ($values as $value) {
            $data[] = $this->convertFieldValueTypeTrait($value);
        }

        return $data;
    }

    /**
     * 単一のフィールド値を適切な型に変換する
     * （電話番号・郵便番号など先頭0を保持する値は文字列のまま返す）
     *
     * @param mixed $value
     * @return mixed
     */
    private function convertFieldValueTypeTrait($value)
    {
        if ($value === '') {
            return null;
        }

        if ($this->isBooleanStringTrait($value)) {
            return strtolower($value) === 'true';
        }

        if (is_numeric($value)) {
            return $this->convertNumericFieldValueTrait($value);
        }

        return $value;
    }

    /**
     * 数値文字列を int/float に変換する
     * 先頭が0で2文字以上の場合は文字列のまま（電話番号・郵便番号等を保持）
     *
     * @param string|int|float $value
     * @return int|float|string
     */
    private function convertNumericFieldValueTrait($value)
    {
        $strVal = (string) $value;

        if (strpos($strVal, '.') !== false) {
            return (float) $value;
        }

        $isLeadingZero = strlen($strVal) > 1 && $strVal[0] === '0';
        if ($isLeadingZero) {
            return $value;
        }

        return (int) $value;
    }

    /**
     * ブール文字列（"true" / "false"）かどうか
     *
     * @param mixed $value
     * @return bool
     */
    private function isBooleanStringTrait($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $lower = strtolower($value);

        return $lower === 'true' || $lower === 'false';
    }
}
