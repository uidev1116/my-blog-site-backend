<?php

use Uri\Rfc3986\Uri as Rfc3986Uri;

/**
 * Static Export Console Runner
 *
 * CLI経由でプロジェクトルートとURLを指定してHTMLを静的エクスポートするための関数群
 *
 * @see console.php
 */

/**
 * エラーメッセージを出力して終了
 *
 * @param string $message
 * @return never
 */
function errorExit(string $message): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit(1);
}

/**
 * 現在のディレクトリパスを取得
 *
 * @return string
 */
function getCurrentDirectory(): string
{
    $pwd = getenv('PWD');
    $currentDir = $pwd ? $pwd : getcwd();

    if ($currentDir === false) {
        errorExit('Unable to determine current directory.');
    }

    return $currentDir;
}

/**
 * index.phpのパスを検証して取得
 *
 * @param string $projectRoot
 * @return string
 */
function getIndexFile(string $projectRoot): string
{
    $indexFile = rtrim($projectRoot, '/') . '/index.php';

    if (!file_exists($indexFile)) {
        errorExit('index.php not found in doc root.');
    }

    return $indexFile;
}

/**
 * URLを解析してパラメータを取得
 *
 * @param string $url
 * @return array{host: string|null, path: string, query: string, isHttps: bool}
 */
function parseUrl(string $url): array
{
    $parsedUrl = Rfc3986Uri::parse($url);

    if ($parsedUrl === null) {
        errorExit("Invalid URL format: {$url}");
    }

    return [
        'host' => $parsedUrl->getHost(),
        'path' => $parsedUrl->getPath() === '' ? '/' : $parsedUrl->getPath(),
        'query' => $parsedUrl->getQuery() ?? '',
        'isHttps' => $parsedUrl->getScheme() === 'https',
    ];
}

/**
 * 仮想サーバー環境を構築
 *
 * @param string $indexFile
 * @param string $url
 * @param array{host: string|null, path: string, query: string, isHttps: bool} $urlParams
 * @param string|null $userAgent
 * @return void
 */
function buildServerEnvironment(string $indexFile, string $url, array $urlParams, ?string $userAgent = null): void
{
    $_POST = [];
    $_SERVER = array_merge($_SERVER, [
        // サーバー固定
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'SCRIPT_FILENAME' => $indexFile,
        'SCRIPT_NAME' => '/index.php',
        // ブラウザ・クライアント
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_REFERER' => $url,
        // ループバック CLI ループでは圧縮された出力を受け取れないため identity を指定する
        'HTTP_ACCEPT_ENCODING' => 'identity',
        'HTTP_ACCEPT_LANGUAGE' => 'ja,en-US;q=0.9,en;q=0.8',
        'HTTP_USER_AGENT' => $userAgent ?? 'a-blog cms (CLI)',
        'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="112", "Google Chrome";v="112"',
        'HTTP_SEC_CH_UA_MOBILE' => '?0',
        'HTTP_SEC_CH_UA_PLATFORM' => '"Windows"',
        // リクエストURLで変化
        'HTTP_HOST' => $urlParams['host'],
        'REQUEST_URI' => $urlParams['path'],
        'QUERY_STRING' => $urlParams['query'],
        'REQUEST_METHOD' => 'GET',
        'HTTPS' => $urlParams['isHttps'] ? 'on' : 'off',
        'SERVER_PORT' => $urlParams['isHttps'] ? 443 : 80,
    ]);
}

/**
 * メイン処理を実行
 *
 * @param array<int, string> $argv
 * @return void
 */
function run(array $argv): void
{
    // CLIのみ実行可能
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Forbidden: CLI only');
    }

    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', 'php://stderr');
    error_reporting(E_ALL);

    set_exception_handler(function (\Throwable $e) {
        fwrite(STDERR, "[uncaught] " . (string)$e . PHP_EOL);
        exit(255);
    });

    register_shutdown_function(function () {
        $e = error_get_last();
        if (!$e) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if (in_array($e['type'], $fatalTypes, true)) {
            fwrite(STDERR, "[shutdown] " . json_encode($e, JSON_UNESCAPED_UNICODE) . PHP_EOL);
            exit(255);
        }

        // Warning/Notice はログだけ（終了コードは変えない）
        fwrite(STDERR, "[shutdown-nonfatal] " . json_encode($e, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    });

    // 引数を取得
    $projectRoot = $argv[1] ?? null;
    $url = $argv[2] ?? null;
    $userAgent = $argv[3] ?? null;

    if ($projectRoot === null) {
        errorExit('Project root must be specified as the first argument.');
    }
    if ($url === null) {
        errorExit('URL must be specified as the second argument.');
    }

    // index.phpを取得
    $indexFile = getIndexFile($projectRoot);

    // URLを解析
    $urlParams = parseUrl($url);

    // 仮想サーバー環境を構築
    buildServerEnvironment($indexFile, $url, $urlParams, $userAgent);

    // セッションを含むかどうかを取得
    $sessionName = getenv('SESSION_NAME');
    $sessionId = getenv('SESSION_ID');
    if ($sessionName && $sessionId) {
        $_COOKIE[$sessionName] = $sessionId;
    }

    // カレントディレクトリをa-blog cmsのルートに設定
    chdir(dirname($indexFile));

    // 実行して出力を取得
    ob_start();
    require $indexFile;
    $html = ob_get_clean();

    // HTTPステータスコードを取得して標準エラー出力に出力
    $statusCode = http_response_code();
    if ($statusCode !== false) {
        fwrite(STDERR, "{$statusCode}\n");
    }

    // HTMLを標準出力に出力
    echo $html;
    exit(0);
}
