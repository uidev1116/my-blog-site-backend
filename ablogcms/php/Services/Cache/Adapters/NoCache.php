<?php

namespace Acms\Services\Cache\Adapters;

use Acms\Services\Cache\Contracts\AdapterInterface;
use Symfony\Component\Cache\CacheItem;

class NoCache implements AdapterInterface
{
    /**
     * キャッシュアイテムの取得
     * @param string $key
     * @return CacheItem
     */
    public function getItem(string $key): CacheItem
    {
        return new CacheItem();
    }

    /**
     * キャッシュがあるか確認
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return false;
    }

    /**
     * キャッシュを取得
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        return false;
    }

    /**
     * キャッシュを設定
     * $lifetimeを指定しない場合はデフォルト値を設定
     *
     * @param string $key
     * @param mixed $value
     * @param int $lifetime
     * @return void
     */
    public function put(string $key, $value, int $lifetime = 0): void
    {
    }

    /**
     * キャッシュアイテムを設定
     * $lifetimeを指定しない場合はデフォルト値を設定
     *
     * @param CacheItem $item
     * @param int $lifetime
     * @return void
     */
    public function putItem(CacheItem $item, int $lifetime = 0): void
    {
    }

    /**
     * キャッシュを削除
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
    }

    /**
     * キャッシュを全削除
     * @return void
     */
    public function flush(): void
    {
    }

    /**
     * 有効期限切れのキャッシュを削除
     */
    public function prune()
    {
    }

    /**
     * タグを指定してキャッシュ削除
     */
    public function invalidateTags($tags = [])
    {
    }
}
