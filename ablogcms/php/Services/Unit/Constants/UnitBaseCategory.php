<?php

declare(strict_types=1);

namespace Acms\Services\Unit\Constants;

/**
 * ユニットの基本カテゴリー
 */
class UnitBaseCategory
{
    public const BASIC = 'basic';

    private static $valueToName = [
        self::BASIC => '基本',
    ];

    /**
     * 定数を取得
     *
     * @return array<string, string>
     */
    public static function getConstants(): array
    {
        return (new \ReflectionClass(__CLASS__))->getConstants();
    }

    /**
     * 定数のキーを取得
     *
     * @return string[]
     */
    public static function getKeys(): array
    {
        return array_keys(static::getConstants());
    }

    /**
     * 定数の値を取得
     *
     * @return string[]
     */
    public static function getValues(): array
    {
        return array_values(static::getConstants());
    }

    /**
     * 定数の値に対応する名前を取得
     *
     * @param string $value
     * @return string
     */
    public static function name(string $value): string
    {
        if (!isset(self::$valueToName[$value])) {
            throw new \RuntimeException(sprintf(
                'Enum %s has no name defined for value %s',
                __CLASS__,
                $value
            ));
        }
        return self::$valueToName[$value];
    }

    /**
     * 定数の名前に対応する値を取得
     *
     * @param string $name
     * @return string
     */
    public static function value(string $name): string
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new \RuntimeException(sprintf(
                'Enum %s has no value defined for name %s',
                __CLASS__,
                $name
            ));
        }
        return constant($const);
    }

    /**
     * 定数の値に対応するカテゴリーを取得
     *
     * @param string $value
     * @return array{
     *   value: string,
     *   name: string,
     * }
     */
    public static function one(string $value): array
    {
        if (!isset(self::$valueToName[$value])) {
            throw new \RuntimeException(sprintf(
                'UnitBaseCategory に value "%s" は存在しません',
                $value
            ));
        }

        return [
            'value' => $value,
            'name' => self::$valueToName[$value],
        ];
    }

    /**
     * すべてのカテゴリーを取得
     *
     * @return array{
     *   value: string,
     *   name: string,
     * }[]
     */
    public static function all(): array
    {
        $result = [];
        foreach (self::$valueToName as $value => $name) {
            $result[] = [
                'value' => $value,
                'name' => $name,
            ];
        }
        return $result;
    }
}
