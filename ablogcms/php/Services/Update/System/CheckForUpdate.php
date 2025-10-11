<?php

namespace Acms\Services\Update\System;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Http;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use stdClass;

class CheckForUpdate
{
    /**
     * メジャーバージョン
     */
    public const MAJOR_VERSION = 1;

    /**
     * マイナーバージョン
     */
    public const MINOR_VERSION = 2;

    /**
     * パッチバージョン
     */
    public const PATCH_VERSION = 3;

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var string
     */
    protected $cache_path;

    /**
     * @var string
     */
    protected $schema_path;

    /**
     * @var string
     */
    protected $jsonString;
    /**
     * @var stdClass
     */
    protected $data;

    /**
     * @var string
     */
    protected $updateVersion;

    /**
     * @var string
     */
    protected $downGradeVersion;

    /**
     * @var string
     */
    protected $changelogUrl;

    /**
     * @var array
     */
    protected $changelogArray;

    /**
     * @var string
     */
    protected $packageUrl;

    /**
     * @var string
     */
    protected $downGradePackageUrl;

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var int
     */
    protected $finalCheckTime;

    /**
     * @var array
     */
    protected $releaseNote;

    /**
     * ValidateVersion constructor.
     *
     * @param string $endpoint
     * @param string $schema_path
     */
    public function __construct(string $endpoint, string $cache_path, string $schema_path)
    {
        $this->endpoint = $endpoint;
        $this->schema_path = $schema_path;
        $this->cache_path = $cache_path;

        try {
            $this->finalCheckTime = LocalStorage::lastModified($this->cache_path);
        } catch (\Exception $e) {
        }
    }

    /**
     * Getter: アップデートバージョン
     *
     * @return string
     */
    public function getUpdateVersion(): string
    {
        return $this->updateVersion;
    }

    /**
     * Getter: アップデートバージョン
     *
     * @return string
     */
    public function getDownGradeVersion(): string
    {
        return $this->downGradeVersion;
    }

    /**
     * Getter: アップグレードパッケージのダウンロードURL
     *
     * @return string
     */
    public function getPackageUrl(): string
    {
        return $this->packageUrl;
    }

    /**
     * Getter: アップグレードパッケージのダウンロードURL
     *
     * @return string
     */
    public function getDownGradePackageUrl(): string
    {
        return $this->downGradePackageUrl;
    }

    /**
     * Getter: アップグレードパッケージの解凍後の本体までのパスのGetter
     *
     * @return string
     */
    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Getter: 最終チェック時間
     *
     * @return int
     */
    public function getFinalCheckTime(): int
    {
        return $this->finalCheckTime;
    }

    /**
     * Getter: Changelog URL
     *
     * @return string
     */
    public function getChangelogUrl(): string
    {
        return $this->changelogUrl;
    }

    /**
     * Changelog
     *
     * @return array
     */
    public function getChangelogArray(): array
    {
        return $this->changelogArray;
    }

    /**
     * Getter: ReleaseNote
     *
     * @return array
     */
    public function getReleaseNote(): array
    {
        return $this->releaseNote;
    }

    /**
     * バージョンアップが存在するか確認
     *
     * @param string $php_version
     * @param int<1, 3> $type
     * @return bool
     */
    public function check($php_version, $type = self::PATCH_VERSION): bool
    {
        $string = $this->request($this->endpoint);

        if ($this->checkForUpdate($string, $php_version, $type)) {
            $this->finalCheckTime = REQUEST_TIME;
            return true;
        }
        return false;
    }

    /**
     * バージョンアップが存在するか確認（キャッシュ利用）
     *
     * @param string $php_version
     * @param int<1, 3> $type
     * @return bool
     */
    public function checkUseCache($php_version, $type = self::PATCH_VERSION): bool
    {
        try {
            $string = LocalStorage::get($this->cache_path);
            if (empty($string)) {
                throw new \RuntimeException('empty');
            }
        } catch (\Exception $e) {
            return false;
        }
        if ($this->checkForUpdate($string, $php_version, $type)) {
            return true;
        }
        return false;
    }

    /**
     * ダウングレードバージョンが存在するか確認（キャッシュ利用）
     *
     * @param string $php_version
     * @return bool
     */
    public function checkDownGradeUseCache($php_version): bool
    {
        try {
            $string = LocalStorage::get($this->cache_path);
            if (empty($string)) {
                throw new \RuntimeException('empty');
            }
        } catch (\Exception $e) {
            return false;
        }
        if ($this->checkForDownGrade($string, $php_version)) {
            return true;
        }
        return false;
    }

    /**
     * 最終マイナーバージョンを取得
     *
     * @param string $currentVersion
     * @return array
     */
    public function getLatestMinorVersion(string $currentVersion): array
    {
        $minorVersion = $this->findLatestMinorVersion($currentVersion);
        if (!$minorVersion) {
            return [];
        }
        [$minPhpVersion, $maxPhpVersion] = $this->getPhpVersionRange($minorVersion->packages);

        return [
            'latestMinorVersion' => $minorVersion->version,
            'minPhpVersion' => $minPhpVersion,
            'maxPhpVersion' => $maxPhpVersion,
        ];
    }

    /**
     * 最新のマイナーバージョンを取得
     *
     * @param string $currentVersion
     * @return stdClass|null
     */
    private function findLatestMinorVersion(string $currentVersion): ?stdClass
    {
        foreach ($this->data->versions as $item) {
            if ($this->isMinorVersion($item->version, $currentVersion)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * 対応PHPバージョン範囲を取得
     *
     * @param array $packages
     * @return array
     */
    private function getPhpVersionRange(array $packages): array
    {
        $min = null;
        $max = null;

        foreach ($packages as $package) {
            if (is_null($min) || version_compare($package->php_min_version, $min, '<')) {
                $min = $package->php_min_version;
            }
            if (is_null($max) || version_compare($package->php_max_version, $max, '>')) {
                $max = $package->php_max_version;
            }
        }
        return [$min, $max];
    }

    /**
     * 実際のチェックバージョン処理
     *
     * @param string $string
     * @param string $php_version
     * @param int<1, 3> $type
     * @return bool
     */
    protected function checkForUpdate($string, $php_version, $type = self::PATCH_VERSION)
    {
        try {
            $php_version = strtolower($php_version);
            $this->decode($string);

            $update_version = $this->checkAcmsVersion($php_version, $type);
            if (!$update_version) {
                return false;
            }
            $this->releaseNote = $this->createReleaseNote($update_version->version);
            $this->updateVersion = $update_version->version;
            $this->changelogUrl = $update_version->changelog->link;
            $this->changelogArray = $update_version->changelog->logs;
            $package = $this->checkPhpVersion($update_version->packages, $php_version);
            if (!$package) {
                return false;
            }
            $this->packageUrl = $package->download;
            $this->rootDir = $package->root_dir;

            return true;
        } catch (\Exception $e) {
            Logger::notice($e->getMessage(), Common::exceptionArray($e));
        }
        return false;
    }

    /**
     * 実際のダウングレードバージョン処理
     *
     * @param string $string
     * @param string $php_version
     * @return bool
     */
    protected function checkForDownGrade($string, $php_version)
    {
        try {
            $php_version = strtolower($php_version);
            $this->decode($string);

            $down_grade_version = $this->checkAcmsDownGradeVersion();
            if (!$down_grade_version) {
                return false;
            }
            $this->downGradeVersion = $down_grade_version->version;
            $package = $this->checkPhpVersion($down_grade_version->packages, $php_version);
            if (!$package) {
                return false;
            }
            $this->downGradePackageUrl = $package->download;
            $this->rootDir = $package->root_dir;

            return true;
        } catch (\Exception $e) {
            Logger::notice($e->getMessage(), Common::exceptionArray($e));
        }
        return false;
    }

    /**
     * phpのバージョンチェック
     *
     * @param object $packages
     * @param string $php_version
     * @return StdClass|null
     */
    protected function checkPhpVersion($packages, $php_version): ?stdClass
    {
        foreach ($packages as $package) {
            $php_min_version = $package->php_min_version;
            $php_max_version = str_replace('x', '99999', $package->php_max_version);
            if (
                1
                && version_compare($php_version, $php_min_version, '>=')
                && version_compare($php_version, $php_max_version, '<=')
            ) {
                return $package;
            }
        }
        return null;
    }

    /**
     * a-blog cmsのバージョンチェック
     *
     * @param string $phpVersion
     * @param int<1, 3> $type
     * @return stdClass|null
     */
    protected function checkAcmsVersion(string $phpVersion, int $type = self::PATCH_VERSION): ?stdClass
    {
        $current = strtolower(VERSION);
        switch ($type) {
            case self::PATCH_VERSION:
                $method = 'isPatchVersion';
                break;
            case self::MINOR_VERSION:
                $method = 'isMinorVersion';
                break;
            case self::MAJOR_VERSION:
                $method = 'isMajorVersion';
                break;
        }
        foreach ($this->data->versions as $item) {
            $version = $item->version;
            if (call_user_func([$this, $method], $version, $current)) {
                if ($this->checkPhpVersion($item->packages, $phpVersion)) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * a-blog cmsのダウングレードバージョンチェック
     *
     * @return stdClass|null
     */
    protected function checkAcmsDownGradeVersion(): ?stdClass
    {
        foreach ($this->data->versions as $item) {
            if ($this->isDownGradeVersion($item->version)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * バージョンに合わせたリリースノート作成
     *
     * @param string $updateCmsVersion
     * @return array
     */
    protected function createReleaseNote($updateCmsVersion)
    {
        if (!property_exists($this->data, 'releaseNote')) {
            return [];
        }
        $allNote = $this->data->releaseNote;
        if (empty($allNote)) {
            return [];
        }
        $partOfNote = [];
        foreach ($allNote as $note) {
            if (
                1
                && version_compare($note->version, strtolower(VERSION), '>')
                && version_compare($note->version, $updateCmsVersion, '<=')
            ) {
                $partOfNote[] = $note;
            }
        }
        return $partOfNote;
    }

    /**
     * ダウングレードバージョンがあるかチェック
     *
     * @param $version
     * @return bool
     */
    protected function isDownGradeVersion($version): bool
    {
        $versionAry = preg_split('/[-+\.\_]/', $version);
        $licenseMajorVersion = intval(substr(LICENSE_SYSTEM_MAJOR_VERSION, 0, 1));
        $licenseMinorVersion = intval(substr(LICENSE_SYSTEM_MAJOR_VERSION, 1));

        $current = "$licenseMajorVersion.$licenseMinorVersion.0";
        $next = $licenseMinorVersion + 1;
        $next = "$licenseMajorVersion.$next.0";
        if (
            1
            && version_compare($version, $current, '>')
            && version_compare($version, $next, '<')
            && intval($versionAry[1]) === $licenseMinorVersion
        ) {
            return true;
        }
        return false;
    }

    /**
     * パッチバージョンがあるか判定
     *
     * @param string $version
     * @param string $current
     * @return bool
     */
    protected function isPatchVersion($version, $current): bool
    {
        $versionAry = preg_split('/[-+\.\_]/', $version);
        $currentAry = preg_split('/[-+\.\_]/', $current);
        $next = (intval($currentAry[1]) + 1);
        $next = "{$currentAry[0]}.{$next}.0";
        if (
            1
            && version_compare($version, $current, '>')
            && version_compare($version, $next, '<')
            && $versionAry[1] === $currentAry[1]
        ) {
            return true;
        }
        return false;
    }

    /**
     * マイナーバージョンがあるか判定
     *
     * @param string $version
     * @param string $current
     * @return bool
     */
    protected function isMinorVersion($version, $current): bool
    {
        $versionAry = preg_split('/[-+\.\_]/', $version);
        $currentAry = preg_split('/[-+\.\_]/', $current);
        $next = (intval($currentAry[0]) + 1);
        $next = "{$next}.0.0";
        if (
            1
            && version_compare($version, $current, '>')
            && version_compare($version, $next, '<')
            && $versionAry[0] === $currentAry[0]
        ) {
            return true;
        }
        return false;
    }

    /**
     * メジャーバージョンがあるか判定
     *
     * @param string $version
     * @param string $current
     * @return bool
     */
    protected function isMajorVersion($version, $current): bool
    {
        $tmp = preg_split('/[-+\.\_]/', $current);
        $next = ++$tmp[0];
        $next = "{$next}.0.0";
        if (
            1
            && version_compare($version, $current, '>')
            && version_compare($version, $next, '>=')
        ) {
            return true;
        }
        return false;
    }

    /**
     * JSONをバリデート & デコード
     *
     * @param string $string
     */
    protected function decode($string): void
    {
        $data = json_decode($string);
        if (!property_exists($data, 'versions') || !property_exists($data, 'releaseNote')) {
            throw new \RuntimeException('取得したアップデートバージョンが記載されたJSONが不正な形式です。');
        }
        $this->data = $data;
    }

    /**
     * Request
     *
     * @param string $endpoint
     * @return mixed
     */
    protected function request($endpoint)
    {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6); // phpcs:ignore
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);

        Http::setCurlProxy($curl);

        $string = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if (!$string || !is_string($string) || $status !== 200) {
            throw new \RuntimeException($status . ' : Failed to get the json');
        }
        if ($charset = mb_detect_encoding($string, 'UTF-8, EUC-JP, SJIS') and 'UTF-8' <> $charset) {
            $string = mb_convert_encoding($string, 'UTF-8', $charset);
        }
        $this->jsonString = (string) $string;
        if ($this->jsonString) {
            LocalStorage::put($this->cache_path, $this->jsonString);
        }
        return $string;
    }
}
