<?php

/**
 * Static Export Console Script
 *
 * CLI経由でプロジェクトルートとURLを指定してHTMLを静的エクスポートするスクリプト
 *
 * Usage:
 *   php console.php <project_root> <url>
 *
 * Example:
 *   php console.php /path/to/ablogcms http://example.com/
 */

require_once __DIR__ . '/ConsoleRunner.php';

run($argv);
