<?php

declare(strict_types=1);

namespace Acms\Traits\Unit;

/**
 * 多言語ユニットのユーティリティトレイト
 * @deprecated 多言語ユニットは非推奨です。新規ユニットは多言語対応していません。
 */
trait UnitMultiLangTrait
{
    /**
     * ユニットのデータを結合する
     *
     * @deprecated 多言語ユニットは非推奨です。新規ユニットは多言語対応していません。
     * @param string[]|string $data
     * @return string
     */
    public function implodeUnitDataTrait($data)
    {
        if (is_array($data)) {
            $data = str_replace(':acms_unit_delimiter:', ':acms-unit-delimiter:', $data);
            $data = implode(':acms_unit_delimiter:', $data);
        }
        if (preg_match('/^(:acms_unit_delimiter:)+$/', $data)) {
            $data = '';
        }
        return $data;
    }

    /**
     * ユニットのデータを分割する
     *
     * @deprecated 多言語ユニットは非推奨です。新規ユニットは多言語対応していません。
     * @param mixed $data
     * @return array
     */
    public function explodeUnitDataTrait($data): array
    {
        if (is_string($data)) {
            $data = explode(':acms_unit_delimiter:', $data);
        }
        if (is_array($data)) {
            return $data;
        }
        return [$data];
    }

    /**
     * ユニットのデータを多言語ユニットを考慮して整形する
     *
     * @deprecated 多言語ユニットは非推奨です。新規ユニットは多言語対応していません。
     * @param mixed $data
     * @param array &$vars
     * @param string $name
     */
    public function formatMultiLangUnitDataTrait($data, &$vars = [], $name = '')
    {
        $dataAry = $this->explodeUnitDataTrait($data);
        foreach ($dataAry as $u => $var) {
            if (is_string($var)) {
                $var = str_replace(':acms-unit-delimiter:', ':acms_unit_delimiter:', $var);
            }
            $suffix = (string) ($u === 0 ? '' : $u + 1);
            $vars["{$name}{$suffix}"] = $var;
            $vars["{$name}{$suffix}:checked#{$var}"] = config('attr_checked');
            $vars["{$name}{$suffix}:selected#{$var}"] = config('attr_selected');
        }
    }

    /**
     * 多言語ユニット用のデリミタを削除する
     *
     * @deprecated 多言語ユニットは非推奨です。新規ユニットは多言語対応していません。
     * @param string $text
     * @return string
     */
    public function removeMultiLangUnitDelimiterTrait(string $text): string
    {
        return str_replace(':acms_unit_delimiter:', '', $text);
    }
}
