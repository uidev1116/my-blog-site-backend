<?php

namespace Acms\Services\Cache\Contracts;

use Symfony\Component\Cache\CacheItem;

interface AdapterInterface
{
    /**
     * キャッシュアイテムの取得
     *
     * @param string $key
     * @return CacheItem
     */
    public function getItem(string $key): CacheItem;

    /**
     * キャッシュがあるか確認
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * キャッシュを取得
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

    /**
     * キャッシュを設定
     * $lifetimeを指定しない場合はデフォルト値を設定
     *
     * @param string $key
     * @param mixed $value
     * @param int $lifetime
     * @return void
     */
    public function put(string $key, $value, int $lifetime = 0): void;

    /**
     * キャッシュアイテムを設定
     * $lifetimeを指定しない場合はデフォルト値を設定
     *
     * @param CacheItem $item
     * @param int $lifetime
     * @return void
     */
    public function putItem(CacheItem $item, int $lifetime = 0): void;

    /**
     * キャッシュを削除
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void;

    /**
     * キャッシュを全削除
     * @return void
     */
    public function flush(): void;
}
