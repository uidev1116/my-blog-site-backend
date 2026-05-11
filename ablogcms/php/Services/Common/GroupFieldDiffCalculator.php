<?php

namespace Acms\Services\Common;

/**
 * グループフィールド (child フィールド) の行削除差分検出。
 *
 * DB 側にある `@path` 値のうち、POST 側に存在しないものを返す純粋関数。
 * image バリエーション (tiny/large/square, .webp) の列挙は行わず、
 * 必要なら呼び出し元で CustomFieldAssetCleaner と組み合わせる。
 */
class GroupFieldDiffCalculator
{
    /**
     * DB 側にあり POST 側に無い path を一意化して返す。
     *
     * 空文字列と重複は除去される。順序は保証しない。
     *
     * @param string[] $dbValue DB 側の @path 値一覧
     * @param string[] $postValue POST 側の @path 値一覧
     * @return string[] 削除対象 path の配列 (一意化済み、順序不定)
     */
    public static function calculateRemovedRows(array $dbValue, array $postValue): array
    {
        $db = array_values(array_unique(array_filter($dbValue, fn($v) => $v !== '')));
        $post = array_values(array_unique(array_filter($postValue, fn($v) => $v !== '')));
        return array_values(array_diff($db, $post));
    }
}
