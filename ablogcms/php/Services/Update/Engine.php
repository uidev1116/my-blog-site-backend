<?php

namespace Acms\Services\Update;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Cache;
use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Application;
use SQL;
use RuntimeException;

class Engine
{
    /**
     * システムファイルのバージョン
     *
     * @var string
     */
    public $systemVersion;

    /**
     * データベースのシステムバージョン
     *
     * @var string
     */
    public $databaseVersion;

    /**
     * 使用していないカラム
     *
     * @var array
     */
    protected $unusedColumn;

    /**
     * config.server.php のパス
     *
     * @var string
     */
    protected $configServerPath;

    /**
     * データベーススキーマ
     *
     * @var \Acms\Services\Update\Database\Schema
     */
    protected $schema;

    /**
     * @var \Acms\Services\Update\Contracts\LoggerInterface
     */
    protected $logger;

    /**
     * 新規追加されたテーブル
     * @var array
     */
    protected $createdTables = [];

    /**
     * Constructor
     *
     * @param \Acms\Services\Update\Contracts\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $DB = DB::singleton(dsn());
        $this->logger = $logger;
        $this->schema = new Database\Schema(dsn());

        $tables = $this->schema->listUp($this->schema->schema);
        foreach ($tables as $table) {
            $this->unusedColumn[$table] = $this->schema->unusedColumns($table);
        }

        $SQL = SQL::newSelect('sequence');
        $SQL->addSelect('sequence_system_version');
        $this->databaseVersion = $DB->query($SQL->get(dsn()), 'one');
        $this->systemVersion = VERSION;
        $this->configServerPath = SCRIPT_DIR . 'config.server.php';
    }

    /**
     * @param string $version
     */
    public function setUpdateVersion($version)
    {
        $this->systemVersion = $version;
    }

    /**
     * バージョンチェック
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function checkUpdates()
    {
        if (version_compare($this->databaseVersion, $this->systemVersion, '=')) {
            return false;
        }
        return true;
    }

    /**
     * データベースのアップデートできるかチェック
     *
     * @param bool $skipVersion
     * @throws \RuntimeException
     */
    public function validate($skipVersion = false)
    {
        // check version
        $check = $this->checkUpdates();
        if (!$skipVersion && !$check) {
            throw new RuntimeException(gettext('アップデートする必要がありません。'));
        }

        // check permission of config.server.php
        if (!LocalStorage::isWritable($this->configServerPath)) {
            throw new RuntimeException(gettext('config.server.php への書き込み権限がありません。'));
        }

        // check in alter table
        if (!$this->schema->checkAlterSystemTablePermission()) {
            throw new RuntimeException(gettext('システムテーブルを変更する権限がありません。'));
        }
    }

    /**
     * データベースのアップデート実行
     */
    public function update()
    {
        $this->logger->message('データベースのアップデートを開始...', 5);
        $this->dbUpdate();
        $this->logger->message('データベースのアップデート完了', 20);
    }

    /**
     * @throws \Exception
     */
    public function dbUpdate()
    {
        try {
            @set_time_limit(0);

            $this->schema->setSchema();
            $this->createTables(); // Compare and Create Tables
            $this->updateNames(); // Resolve Should Rename Columns
            $this->updateEngines(); // Compare and Change Engine
            $this->updateColumns(); // Compare and ADD-CHANGE Columns
            $this->updateIndexs(); // Clear and Make Indexies
            $this->updateConfigServerPhp(); // Rebuild config.server.php
            $this->updateSepecificRule(); // Sepecific Update Process
            $this->updateSequenceSystemVersion(); // Update Sequence System Version

            // キャッシュを削除
            if (LocalStorage::exists(CACHE_DIR)) {
                $path = CACHE_DIR . '*.php';
                $config_files = glob($path);
                if (is_array($config_files)) {
                    foreach (glob($path) as $val) {
                        LocalStorage::remove($val);
                    }
                }
            }
            Cache::flush('page');
            Cache::flush('template');
            Cache::flush('config');
            Cache::flush('field');
            Cache::flush('module');

            $purifier = Application::make('html-purifier');
            $purifier->clearCache();

            $twig = Application::make('template.twig');
            $twig->clearCache();

            $this->databaseVersion = $this->systemVersion;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * データベース定義と現在のデータベースに差異があるかチェック
     *
     * @return bool
     */
    public function compareDatabase()
    {
        if ($this->schema->compareTables()) {
            return false;
        }
        $tables = $this->schema->listUp($this->schema->schema);
        foreach ($tables as $tb) {
            $res = $this->schema->compareColumns($tb);
            if (!empty($res['add'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 新しいテーブルの作成
     */
    protected function createTables()
    {
        $diff = $this->schema->compareTables();
        if (!empty($diff)) {
            $this->schema->createTables($diff, $this->schema->indexDefine);
            $this->createdTables = $diff;
        }
    }

    /**
     * データベースのカラム名のアップデート
     */
    protected function updateNames()
    {
        $this->schema->resolveRenames();
    }

    /**
     * テーブルのエンジンをアップデート
     */
    protected function updateEngines()
    {
        $this->schema->resolveEngines();
    }

    /**
     * データベースのカラムをアップデート
     */
    protected function updateColumns()
    {
        $tables = $this->schema->listUp($this->schema->schema);
        foreach ($tables as $tb) {
            if (in_array($tb, $this->createdTables, true)) {
                continue;
            }
            $res = $this->schema->compareColumns($tb);
            $this->schema->resolveColumns($tb, $res['add'], $res['change']);
        }
    }

    /**
     * データベースのインデックス情報をアップデート
     */
    protected function updateIndexs()
    {
        $tables = $this->schema->listUp($this->schema->define);
        foreach ($tables as $tb) {
            if (in_array($tb, $this->createdTables, true)) {
                continue;
            }
            $res = $this->schema->compareIndex($tb);
            $this->schema->makeIndex($tb, $res);
        }
    }

    /**
     * config.server.php のアップデート
     */
    protected function updateConfigServerPhp()
    {
        $config = new System\ConfigServer();
        $config->update($this->configServerPath);
    }

    /**
     * 例外的なルールのアップデート処理
     */
    protected function updateSepecificRule()
    {
        $rule = new Database\Rule();
        $rule->update($this->databaseVersion, $this->systemVersion);
    }

    /**
     * sequenceテーブルのバージョンをアップデート
     */
    protected function updateSequenceSystemVersion()
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newUpdate('sequence');
        $SQL->addUpdate('sequence_system_version', $this->systemVersion);
        $DB->query($SQL->get(dsn()), 'exec');
    }
}
