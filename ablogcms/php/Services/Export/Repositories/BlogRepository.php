<?php

namespace Acms\Services\Export\Repositories;

use ACMS_RAM;
use Field;

class BlogRepository
{
    /**
     * ブログ名を取得
     *
     * @param int $bid
     * @return string
     */
    public function getBlogName(int $bid): string
    {
        return ACMS_RAM::blogName($bid);
    }

    /**
     * ブログURLを取得
     *
     * @param int $bid
     * @return string
     */
    public function getBlogUrl(int $bid): string
    {
        return acmsLink([
            'bid' => $bid,
        ], false);
    }

    /**
     * ブログフィールドを取得
     *
     * @param int $bid
     * @return Field
     */
    public function getBlogField(int $bid): Field
    {
        return loadBlogField($bid);
    }

    /**
     * ブログの生成日時を取得
     *
     * @param int $bid
     * @return string
     */
    public function getBlogGeneratedDatetime(int $bid): string
    {
        return ACMS_RAM::blogGeneratedDatetime($bid);
    }
}
