<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Storage;
use Acms\Services\Facades\Http;

class ACMS_POST_Publish_Apply extends ACMS_POST_Publish
{
    public $pointer = null;

    function post()
    {
        if (!IS_LICENSED) {
            return false;
        }

        if (roleAvailableUser()) {
            if (!roleAuthorization('publish_exec', BID)) {
                return false;
            }
        } else {
            if (!sessionWithCompilation()) {
                return false;
            }
        }

        if (BID != 1) {
            $ParentConfig = loadConfig(ACMS_RAM::blogParent(BID));
            if ('on' != $ParentConfig->get('publish_children_allow')) {
                return false;
            }
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
        for ($i = 0; $i < $max; $i++) {
            $uri = $resources[$i];
            $layout = $layoutOnly[$i];
            $theme = $tgtTheme[$i];
            $path = $tgtPath[$i];
            $this->pointer = md5($uri . $theme . $path);

            if (!preg_match('@^/@', $path)) {
                $path = '/' . $path;
            }
            if (!$this->validateUri($uri)) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $path,
                    'message' => 'URLが不正です',
                ];
                continue;
            }
            if (!$this->isWritable($basePath . $theme)) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $basePath . $theme,
                    'message' => '書き込み権限がありません',
                ];
                continue;
            }
            $fullpath = $basePath . $theme . $path;

            // html, json, xml 拡張子以外はNG
            $extesion = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extesion, ['html', 'json', 'xml', 'csv'], true)) {
                $this->addError("拡張子は「html, json, xml, csv」のみ許可されています");
                continue;
            }
            // ディレクトリトラバーサル攻撃対策
            if (
                !Storage::validateDirectoryTraversalPath(THEMES_DIR . $theme, THEMES_DIR, false) ||
                !Storage::validateDirectoryTraversalPath($fullpath, THEMES_DIR . $theme, false)
            ) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $path,
                    'message' => '不正なパス指定です',
                ];
                continue;
            }
            // 書き込み権限確認
            if (!$this->isExists($fullpath)) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $path,
                    'message' => '書き込み権限がありません',
                ];
                continue;
            }
            // リクエスト
            try {
                $req = Http::init($uri, 'GET');
                $ua = 'publish_ablogcms/' . VERSION;
                if ($layout === 'layout') {
                    $headers['User-Agent'] = ONLY_BUILD_LAYOUT;
                }
                $req->setRequestHeaders([
                    'Accept-Language: ' . HTTP_ACCEPT_LANGUAGE,
                    'User-Agent ' . $ua,
                ]);
                $response = $req->send();
                if (strpos(Http::getResponseHeader('http_code'), '200') === false) {
                    throw new RuntimeException(Http::getResponseHeader('http_code'));
                }
                $body = $response->getResponseBody();

                if (!!($fp = fopen($fullpath, 'w'))) {
                    fwrite($fp, $body);
                    fclose($fp);

                    $successLog[] = [
                        'url' => $uri,
                        'path' => $path,
                    ];
                } else {
                    $errorLog[] = [
                        'url' => $uri,
                        'path' => $path,
                        'message' => '書き込みに失敗しました',
                    ];
                }
            } catch (Exception $e) {
                $errorLog[] = [
                    'url' => $uri,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ];
            }
        }
        if (empty($errorLog) && count($successLog) > 0) {
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

    function validateUri(&$uri)
    {
        $uri = setGlobalVars($uri);
        if (Common::isSafeUrl($uri)) {
            return true;
        }
        $parsed = parse_url($uri);
        if ($parsed === false) {
            $this->addError("不正なURLです。URLの形式が不正です。");
            return false;
        }
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
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
        if ($host = parse_url($uri, PHP_URL_HOST)) {
            if (in_array($host, $whiteList, true)) {
                return true;
            }
        }
        $this->addError("不正なURLです。自ホスト以外のホストを指定する場合は「.env」の「TEMPLATE_EXPORT_WHITE_LIST」を指定ください。（{$uri}）");
        return false;
    }

    function isWritable($path)
    {
        if (LocalStorage::isWritable($path)) {
            return true;
        } else {
            $this->addError("不正なパスです");
            return false;
        }
    }

    function isExists($path)
    {
        if (LocalStorage::exists($path)) {
            if ($this->isWritable($path)) {
                return true;
            } else {
                $this->addError("書き込み権限がありません");
                return false;
            }
        } else {
            return true;
        }
    }
}
