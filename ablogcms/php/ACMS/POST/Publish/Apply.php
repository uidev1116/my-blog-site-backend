<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\PageGeneration\PageGenerationService;
use Uri\WhatWg\Url as WhatWgUrl;

class ACMS_POST_Publish_Apply extends ACMS_POST_Publish
{
    function post()
    {
        if (!$this->canPublish(BID)) {
            $this->addError('権限がありません');
            return $this->Post;
        }

        $Config = loadConfig(BID);
        $resources = $Config->getArray('publish_resource_uri');
        $layoutOnly = $Config->getArray('publish_layout_only');
        $tgtTheme = $Config->getArray('publish_target_theme');
        $tgtPath = $Config->getArray('publish_target_path');

        $resourceCnt = count($resources);
        $layoutOnlyCnt = count($layoutOnly);
        $tgtThemeCnt = count($tgtTheme);
        $tgtPathCnt = count($tgtPath);

        $max = min($resourceCnt, $layoutOnlyCnt, $tgtThemeCnt, $tgtPathCnt);
        $basePath = SCRIPT_DIR . THEMES_DIR;

        $successLog = [];
        $errorLog = [];
        $pageGenerationService = new PageGenerationService();

        for ($i = 0; $i < $max; $i++) {
            $uri = $resources[$i];
            $layout = $layoutOnly[$i];
            $theme = $tgtTheme[$i];
            $path = $tgtPath[$i];
            if (!preg_match('@^/@', $path)) {
                $path = '/' . $path;
            }
            $fullpath = $basePath . $theme . $path;

            try {
                if (!$this->validateUri($uri)) {
                    throw new RuntimeException('URLが不正です');
                }
                if (!$this->validatePath($basePath, $theme, $path)) {
                    throw new RuntimeException('不正なパス指定です');
                }
                if (!$this->validateExtension($path)) {
                    throw new RuntimeException('拡張子は「html, json, xml, csv」のみ許可されています');
                }
                if (!$this->isWritable($basePath . $theme)) {
                    throw new RuntimeException('ディレクトリに書き込み権限がありません');
                }
                $userAgent = 'publish_ablogcms/' . VERSION;
                if ($layout === 'layout') {
                    $userAgent = ONLY_BUILD_LAYOUT;
                }
                $pageGenerationService->addPage(url: $uri, destinationPathname: $fullpath, userAgent: $userAgent);
            } catch (Exception $e) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ];
                continue;
            }
        }
        $results = $pageGenerationService->run(maxParallel: 3, listener: null, withData: true);
        foreach ($results as $result) {
            try {
                $statusCode = $result->getStatusCode();
                $page = $result->getPage();
                $data = $result->getData();

                if ($result->isSuccess() && $statusCode === 200 && $data !== null && $data !== '') {
                    LocalStorage::put($page->getDestinationPathname(), $data);
                    $successLog[] = [
                        'url' => $page->getUrl(),
                        'path' => $page->getDestinationPathname(),
                    ];
                } else {
                    $message = $result->getProcessResult()->getStderr();
                    if ($message === '') {
                        if ($statusCode !== 200) {
                            $message = sprintf(gettext('HTTPステータスコード: %d'), $statusCode);
                        } elseif ($data === null || $data === '') {
                            $message = gettext('レスポンスが空です');
                        } else {
                            $message = gettext('書き出しに失敗しました');
                        }
                    }
                    $errorLog[] = [
                        'url' => $page->getUrl(),
                        'path' => $page->getDestinationPathname(),
                        'message' => $message,
                    ];
                }
            } catch (Exception $e) {
                $this->addError($e->getMessage());
            }
        }

        if (!$errorLog && count($successLog) > 0) {
            $this->addMessage(gettext('書き出しに成功しました'));
            AcmsLogger::info('テンプレートの書き出しに成功しました', $successLog);
        } else {
            foreach ($errorLog as $error) {
                $this->addError($error['message'] . ' (url=' . $error['url'] . ' path=' . $error['path'] . ')');
            }
            AcmsLogger::warning('テンプレート書き出しに失敗しました', $errorLog);
        }
        return $this->Post;
    }

    /**
     * 書き出し権限を確認する
     *
     * @param int $bid
     * @return bool
     */
    private function canPublish(int $bid): bool
    {
        if (!IS_LICENSED) {
            return false;
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('publish_exec', $bid)) {
                return false;
            }
        } else {
            if (!sessionWithCompilation()) {
                return false;
            }
        }
        if ($bid !== RBID) { // @phpstan-ignore-line
            $ParentConfig = loadConfig(ACMS_RAM::blogParent($bid));
            if ('on' !== $ParentConfig->get('publish_children_allow')) {
                return false;
            }
        }
        return true;
    }

    /**
     * URLを検証する
     *
     * @param string $uri
     * @return bool
     */
    private function validateUri(&$uri)
    {
        $uri = setGlobalVars($uri);
        if (Common::isSafeUrl($uri)) {
            return true;
        }
        $parsed = WhatWgUrl::parse($uri);
        if ($parsed === null) {
            $this->addError("不正なURLです。URLの形式が不正です。");
            return false;
        }
        // WHATWG パーサは scheme を小文字に正規化するため、`HTTP://...` でも判定可能
        if (!in_array($parsed->getScheme(), ['http', 'https'], true)) {
            $this->addError("不正なURLです。URLの形式が不正です。");
            return false;
        }
        $whiteList = [];
        $whiteListStr = env('TEMPLATE_EXPORT_WHITE_LIST', '');
        if ($whiteListStr) {
            $whiteList = explode(',', $whiteListStr);
            $whiteList = array_map(function ($item) {
                return trim($item);
            }, $whiteList);
        }
        // WHATWG パーサで ASCII IDN に正規化したホストとホワイトリストを比較する
        if ($host = WhatWgUrl::parse($uri)?->getAsciiHost()) {
            if (in_array($host, array_map('strtolower', $whiteList), true)) {
                return true;
            }
        }
        $this->addError("不正なURLです。自ホスト以外のホストを指定する場合は「.env」の「TEMPLATE_EXPORT_WHITE_LIST」を指定ください。（{$uri}）");
        return false;
    }

    /**
     * パスを検証する
     *
     * @param string $basePath
     * @param string $theme
     * @param string $path
     * @return bool
     */
    private function validatePath(string $basePath, string $theme, string $path): bool
    {
        if (!LocalStorage::validateDirectoryTraversalPath(THEMES_DIR . $theme, THEMES_DIR, false)) {
            return false;
        }
        if (!LocalStorage::validateDirectoryTraversalPath($basePath . $theme . $path, THEMES_DIR . $theme, false)) {
            return false;
        }
        return true;
    }

    /**
     * 拡張子を検証する
     *
     * @param string $path
     * @return bool
     */
    private function validateExtension(string $path): bool
    {
        $extesion = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extesion, ['html', 'json', 'xml', 'csv'], true)) {
            return false;
        }
        return true;
    }

    /**
     * 書き込み権限を確認する
     *
     * @param string $path
     * @return bool
     */
    private function isWritable(string $path): bool
    {
        return LocalStorage::isWritable($path);
    }
}
