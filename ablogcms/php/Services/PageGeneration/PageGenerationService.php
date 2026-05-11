<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration;

use Acms\Services\Process\ProcessPool;
use Acms\Services\Process\ProcessTask;
use Acms\Services\Process\ProcessResult;
use Acms\Services\Process\ProcOpenRunner;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\PageGeneration\Entities\EntryPage;
use Acms\Services\PageGeneration\Handler\PageGenerationResponseHandler;
use Acms\Services\PageGeneration\Handler\PageGenerationErrorHandler;
use Acms\Services\PageGeneration\Handler\PageGenerationTaskStartHandler;
use Acms\Services\PageGeneration\Contracts\PageGenerationListenerInterface;

class PageGenerationService implements PageGenerationListenerInterface
{
    /**
     * @var Page[]
     */
    private array $pages = [];

    /**
     * @var ProcessPool
     */
    private ProcessPool $pool;

    /**
     * ページを生成
     *
     * @param int $maxParallel
     * @param PageGenerationListenerInterface|null $listener
     * @param bool $withData
     * @return list<PageGenerationResult>
     */
    public function run(
        int $maxParallel,
        ?PageGenerationListenerInterface $listener = null,
        bool $withData = false
    ): array {
        $listener ??= $this;
        $runner = new ProcOpenRunner();
        $responseHandler = new PageGenerationResponseHandler($listener, $withData);
        $errorHandler = new PageGenerationErrorHandler($listener);
        $taskStartHandler = new PageGenerationTaskStartHandler($listener);

        $consoleScriptPath = $this->getConsoleScriptPath();
        $phpBinPath = $this->getPhpBinPath();

        $this->pool = new ProcessPool(
            maxParallel: $maxParallel,
            runner: $runner,
            responseHandler: $responseHandler,
            errorHandler: $errorHandler,
            taskStartHandler: $taskStartHandler
        );

        foreach ($this->pages as $page) {
            $command = [$phpBinPath, $consoleScriptPath, SCRIPT_DIR, $page->getUrl()];
            if ($page->getUserAgent() !== null) {
                $command[] = $page->getUserAgent();
            }
            $env = null;
            if ($page->isWithSession() && SESSION_NAME && ACMS_SID) { // @phpstan-ignore-line
                $env = [
                    'SESSION_NAME' => SESSION_NAME,
                    'SESSION_ID' => ACMS_SID,
                ];
            }
            $this->pool->addTask(new ProcessTask(
                command: $command,
                timeoutSeconds: 300.0,
                env: $env,
                context: ['page' => $page],
            ));
        }

        /** @var list<PageGenerationResult> $results */
        $results = $this->pool->run();
        return $results;
    }

    /**
     * ページを追加
     *
     * @param string $url
     * @param string $destinationPathname
     * @param string|null $userAgent
     * @param bool $withSession
     * @return void
     */
    public function addPage(string $url, string $destinationPathname, ?string $userAgent = null, bool $withSession = false): void
    {
        $this->pages[] = new Page($url, $destinationPathname, $userAgent, $withSession);
    }

    /**
     * エントリーページを追加
     *
     * @param string $url
     * @param string $destinationPathname
     * @param integer $entryId
     * @param string|null $userAgent
     * @return void
     */
    public function addEntryPage(string $url, string $destinationPathname, int $entryId, ?string $userAgent = null, bool $withSession = false): void
    {
        $this->pages[] = new EntryPage($url, $destinationPathname, $entryId, $userAgent, $withSession);
    }

    /**
     * 停止を要求する
     *
     * @return void
     */
    public function requestStop(): void
    {
        $this->pool->requestStop();
    }

    /**
     * 成功時の処理
     *
     * @param Page $page
     * @param string $stdout
     * @param int $statusCode
     * @param ProcessResult $result
     * @return void
     */
    public function onPageGenerationSuccess(Page $page, string $stdout, int $statusCode, ProcessResult $result): void
    {
        // デフォルトでは何もしない
    }

    /**
     * エラー時の処理
     *
     * @param Page $page
     * @param int $statusCode
     * @param ProcessResult $result
     * @return void
     */
    public function onPageGenerationError(Page $page, int $statusCode, ProcessResult $result): void
    {
        // デフォルトでは何もしない
    }

    /**
     * 開始時の処理
     *
     * @param Page $page
     * @return bool
     */
    public function onPageGenerationStart(Page $page): bool
    {
        return true;
    }

    /**
     * phpの実行パスを取得
     *
     * @return string
     */
    private function getPhpBinPath(): string
    {
        return env('PHP_BIN_PATH', 'php');
    }

    /**
     * console.phpスクリプトのパスを取得
     *
     * @return string
     */
    private function getConsoleScriptPath(): string
    {
        $scriptPath = __DIR__ . '/console.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('console.php not found: ' . $scriptPath);
        }
        return $scriptPath;
    }
}
