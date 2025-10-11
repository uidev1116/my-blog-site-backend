<?php

namespace Acms\Services\Cache;

use Acms\Services\Cache\Adapters\Standard;
use Acms\Services\Cache\Adapters\Tag;
use Acms\Services\Cache\Adapters\NoCache;
use Acms\Services\Cache\Exceptions\NotFoundException;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Acms\Services\Cache\Adapters\Custom\DatabaseTagAwareAdapter;
use AcmsLogger;

class CacheManager
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * construct
     */
    public function __construct()
    {
        $this->config = include(PHP_DIR . 'config/cache.php');
        $this->cacheDir = CACHE_DIR;
    }

    /**
     * テンプレート用キャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function template()
    {
        return $this->createStandardCache('template', $this->config['type']['template']);
    }

    /**
     * フィールド用キャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function field()
    {
        return $this->createStandardCache('field', $this->config['type']['field']);
    }

    /**
     * 一時的に使えるキャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function temp()
    {
        return $this->createStandardCache('temp', $this->config['type']['temp']);
    }

    /**
     * モジュール用キャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function module()
    {
        return $this->createStandardCache('module', $this->config['type']['module']);
    }

    /**
     * コンフィグ用キャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function config()
    {
        static $cache = null;
        if ($cache) {
            return $cache;
        }
        if (defined('IS_SETUP') && IS_SETUP) {
            $cache = new NoCache();
            return $cache;
        }
        try {
            $config = $this->config['type']['config'];
            $driver = $this->createTagDriver($config['driver'], $this->getNameSpace($config['namespace']), $config['lifetime']);
            $cache = new Tag($driver);
        } catch (NotFoundException $e) {
            AcmsLogger::warning(
                'キャッシュ機能: 利用可能なキャッシュドライバーが見つかりませんでした。cms 設置ディレクトリ直下の .env ファイルを確認してください。',
                [
                    'type' => 'config',
                    'driver' => $config['driver']
                ]
            );
            $cache = new NoCache();
        } catch (\Exception $e) {
            AcmsLogger::warning('キャッシュ機能: ' . $e->getMessage());
            $cache = new NoCache();
        }
        return $cache;
    }

    /**
     * ページ用キャッシュ
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    public function page()
    {
        static $cache = null;
        if ($cache) {
            return $cache;
        }
        if (defined('IS_SETUP') && IS_SETUP) {
            $cache = new NoCache();
            return $cache;
        }
        try {
            $config = $this->config['type']['page'];
            $driver = $this->createTagDriver($config['driver'], $this->getNameSpace($config['namespace']));
            $cache = new Tag($driver);
        } catch (NotFoundException $e) {
            AcmsLogger::warning(
                'キャッシュ機能: 利用可能なキャッシュドライバーが見つかりませんでした。cms 設置ディレクトリ直下の .env ファイルを確認してください。',
                [
                    'type' => 'page',
                    'driver' => $config['driver']
                ]
            );
            $cache = new NoCache();
        } catch (\Exception $e) {
            AcmsLogger::warning('キャッシュ機能: ' . $e->getMessage());
            $cache = new NoCache();
        }
        return $cache;
    }

    /**
     * タイプ別のキャッシュの全クリア
     */
    public function flush($type)
    {
        if (method_exists($this, $type)) {
            $cache = $this->{$type}(); // @phpstan-ignore-line
            $cache->flush();
        }
    }

    /**
     * 全てのキャッシュをクリア
     */
    public function allFlush()
    {
        $this->flush('page');
        $this->flush('template');
        $this->flush('config');
        $this->flush('field');
        $this->flush('temp');
        $this->flush('module');
    }

    /**
     * タイプ別の有効期限切れキャッシュを削除
     */
    public function prune($type)
    {
        if (method_exists($this, $type)) {
            $cache = $this->{$type}(); // @phpstan-ignore-line
            $cache->prune();
        }
    }

    /**
     * 全ての有効期限切れキャッシュを削除
     */
    public function allPrune()
    {
        $this->prune('page');
        $this->prune('template');
        $this->prune('config');
        $this->prune('field');
        $this->prune('temp');
        $this->prune('module');
    }

    /**
     * 標準キャッシュの生成
     *
     * @param string $name
     * @param array $config
     * @return \Acms\Services\Cache\Contracts\AdapterInterface
     */
    protected function createStandardCache($name, $config)
    {
        static $cache = [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }
        if (defined('IS_SETUP') && IS_SETUP) {
            $cache[$name] = new NoCache();
            return $cache[$name];
        }
        try {
            $driver = $this->createDriver(
                $config['driver'],
                $this->getNameSpace($config['namespace']),
                $config['lifetime']
            );
            $cache[$name] = new Standard($driver);
        } catch (NotFoundException $e) {
            AcmsLogger::warning(
                'キャッシュ機能: 利用可能なキャッシュドライバーが見つかりませんでした。cms 設置ディレクトリ直下の .env ファイルを確認してください。',
                [
                    'type' => $name,
                    'driver' => $config['driver']
                ]
            );
            $cache[$name] = new NoCache();
        } catch (\Exception $e) {
            AcmsLogger::warning('キャッシュ機能: ' . $e->getMessage());
            $cache[$name] = new NoCache();
        }

        return $cache[$name];
    }

    /**
     * 標準キャッシュドライバーの作成
     *
     * @param string $drivers
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\AdapterInterface
     *
     * @throws NotFoundException
     */
    protected function createDriver($drivers, $namespace, $lifetime = 0)
    {
        $drivers = array_map('trim', explode('|', $drivers));
        $useDriver = null;
        foreach ($drivers as $driver) {
            $method = 'can' . ucwords($driver) . 'Driver';
            if (method_exists($this, $method)) {
                if ($this->{$method}()) { // @phpstan-ignore-line
                    $useDriver = $driver;
                    break;
                }
            }
        }
        if (empty($useDriver)) {
            throw new NotFoundException('Cache driver is not found.');
        }
        $createMethod = 'create' . ucwords($useDriver) . 'Driver';
        if (method_exists($this, $createMethod)) {
            return $this->{$createMethod}($namespace, $lifetime); // @phpstan-ignore-line
        }
        throw new NotFoundException('Cache driver is not found.');
    }

    /**
     * タグ対応キャッシュドライバーの作成
     *
     * @param string $drivers
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface
     *
     * @throws NotFoundException
     */
    protected function createTagDriver($drivers, $namespace, $lifetime = 0)
    {
        $drivers = array_map('trim', explode('|', $drivers));
        $useDriver = null;
        foreach ($drivers as $driver) {
            $method = 'can' . ucwords($driver) . 'TagDriver';
            if (method_exists($this, $method)) {
                if ($this->{$method}()) { // @phpstan-ignore-line
                    $useDriver = $driver;
                    break;
                }
            }
        }
        if (empty($useDriver)) {
            throw new NotFoundException('Cache driver is not found.');
        }
        $createMethod = 'create' . ucwords($useDriver) . 'TagDriver';
        if (method_exists($this, $createMethod)) {
            return $this->{$createMethod}($namespace, $lifetime); // @phpstan-ignore-line
        }
        throw new NotFoundException('Cache driver is not found.');
    }

    /**
     * phpキャッシュドライバーが使用可能か
     * @return boolean
     */
    protected function canPhpDriver()
    {
        return function_exists('opcache_get_status');
    }

    /**
     * phpキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\PhpFilesAdapter
     */
    protected function createPhpDriver($namespace, $lifetime)
    {
        return new PhpFilesAdapter(
            $namespace,
            $defaultLifetime = $lifetime,
            $directory = $this->cacheDir
        );
    }

    /**
     * ファイルキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canFileDriver()
    {
        return true;
    }

    /**
     * ファイルキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\FilesystemAdapter
     */
    protected function createFileDriver($namespace, $lifetime)
    {
        return new FilesystemAdapter($namespace, $lifetime, $this->cacheDir);
    }

    /**
     * データベースキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canDatabaseDriver()
    {
        return true;
    }

    /**
     * データベースキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Acms\Services\Cache\Adapters\Custom\DatabaseTagAwareAdapter
     */
    protected function createDatabaseDriver($namespace, $lifetime)
    {
        return new DatabaseTagAwareAdapter($namespace, $lifetime);
    }

    /**
     * メモリーキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canMemoryDriver()
    {
        return true;
    }

    /**
     * メモリーキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\ArrayAdapter
     */
    protected function createMemoryDriver($namespace, $lifetime)
    {
        return new ArrayAdapter($lifetime, false, 100, 1000);
    }

    /**
     * APCuキャッシュドライバーが使用可能か
     * @return boolean
     */
    protected function canApcuDriver()
    {
        return function_exists('apcu_enabled') && \apcu_enabled();
    }

    /**
     * APCuキャッシュドライバーを作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\ApcuAdapter
     */
    protected function createApcuDriver($namespace, $lifetime)
    {
        return new ApcuAdapter($namespace, $lifetime, null);
    }

    /**
     * Redisキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canRedisDriver()
    {
        return true;
    }

    /**
     * Redisキャッシュドライバーを作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\RedisAdapter
     */
    protected function createRedisDriver($namespace, $lifetime)
    {
        $redisInfo = $this->config['drivers']['redis']['connection'];
        $client = $this->createRedisClient($redisInfo['host'], $redisInfo['port'], $redisInfo['password'], $redisInfo['db']);

        return new RedisAdapter($client, $namespace, $lifetime);
    }

    /**
     * ファイルキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canFileTagDriver()
    {
        return true;
    }

    /**
     * ファイルキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter
     */
    protected function createFileTagDriver($namespace, $lifetime)
    {
        return new FilesystemTagAwareAdapter($namespace, $lifetime, $this->cacheDir);
    }

    /**
     * DBキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canDatabaseTagDriver()
    {
        return true;
    }

    /**
     * DBキャッシュドライバーの作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Acms\Services\Cache\Adapters\Custom\DatabaseTagAwareAdapter
     */
    protected function createDatabaseTagDriver($namespace, $lifetime)
    {
        return new DatabaseTagAwareAdapter($namespace, $lifetime);
    }

    /**
     * Redisキャッシュドライバーが使用可能か
     * @return true
     */
    protected function canRedisTagDriver()
    {
        return true;
    }

    /**
     * Redisキャッシュドライバーを作成
     * @param string $namespace
     * @param int $lifetime
     * @return \Symfony\Component\Cache\Adapter\RedisTagAwareAdapter
     */
    protected function createRedisTagDriver($namespace, $lifetime)
    {
        $redisInfo = $this->config['drivers']['redis']['connection'];
        $client = $this->createRedisClient($redisInfo['host'], $redisInfo['port'], $redisInfo['password'], $redisInfo['db']);

        return new RedisTagAwareAdapter($client, $namespace, $lifetime);
    }

    /**
     * Redisクライアントを作成
     * @param string $host
     * @param int $port
     * @param string $password
     * @param string $db
     */
    protected function createRedisClient($host, $port, $password = '', $db = '')
    {
        $connection = 'redis://';
        if (!empty($password)) {
            $connection .= "$password/$host";
        } else {
            $connection .= $host;
        }
        $connection .= ":$port";
        if (!empty($db)) {
            $connection .= "/$db";
        }
        // PHPStan回避のための明示的存在チェック
        if (!class_exists(\Redis::class) && !class_exists(\RedisCluster::class)) {
            throw new \RuntimeException('Redis extension is not available');
        }
        return RedisAdapter::createConnection($connection);
    }

    /**
     * 他システムの衝突を避けるため、ネームスペースにドメインからのハッシュを付与
     *
     * @param string $namespace
     * @return string
     */
    protected function getNameSpace($namespace)
    {
        return $namespace . '-' . md5(DOMAIN . DIR_OFFSET);
    }
}
