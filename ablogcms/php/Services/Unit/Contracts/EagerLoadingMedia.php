<?php

namespace Acms\Services\Unit\Contracts;

interface EagerLoadingMedia
{
    /**
     * 事前読み込みメディアを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEagerLoadedMedia(): array;

    /**
     * 事前読み込みメディアを設定
     *
     * @param array<int, array<string, mixed>> $media
     * @return void
     */
    public function setEagerLoadedMedia(array $media): void;
}
