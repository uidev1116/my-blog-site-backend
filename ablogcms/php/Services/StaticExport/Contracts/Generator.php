<?php

namespace Acms\Services\StaticExport\Contracts;

use Acms\Services\PageGeneration\Contracts\PageGenerationListenerInterface;
use Acms\Services\PageGeneration\PageGenerationService;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\StaticExport\Destination;
use Acms\Services\StaticExport\Logger;
use Acms\Services\StaticExport\Compiler as PublishCompiler;
use Acms\Services\Process\ProcessResult;
use ACMS_RAM;

abstract class Generator implements PageGenerationListenerInterface
{
    /**
     * @var int
     */
    protected $targetBlogId;

    /**
     * @var string
     */
    protected $targetBlogName;

    /**
     * @var \Acms\Services\StaticExport\Compiler
     */
    protected $compiler;

    /**
     * @var \Acms\Services\StaticExport\Destination
     */
    protected $destination;

    /**
     * @var \Acms\Services\StaticExport\Logger
     */
    protected $logger;

    /**
     * @var int
     */
    protected $maxPublishCount;

    /**
     * @var \Acms\Services\PageGeneration\PageGenerationService
     */
    protected $pageGenerationService;

    /**
     * Generator constructor.
     *
     * @param int $targetBlogId
     * @param \Acms\Services\StaticExport\Compiler $compiler
     * @param \Acms\Services\StaticExport\Destination $destination
     * @param \Acms\Services\StaticExport\Logger $logger
     * @param int $maxPublishCount
     */
    public function __construct(
        int $targetBlogId,
        PublishCompiler $compiler,
        Destination $destination,
        Logger $logger,
        int $maxPublishCount = 5
    ) {
        $this->targetBlogId = $targetBlogId;
        $this->targetBlogName = ACMS_RAM::blogName($targetBlogId) ?? '不明なブログ';
        $this->compiler = $compiler;
        $this->destination = $destination;
        $this->logger = $logger;
        if ($maxPublishCount > 0) {
            $this->maxPublishCount = $maxPublishCount;
        }
        $this->pageGenerationService = new PageGenerationService();
    }

    /**
     * 書き出しのために、ページ取得を開始する
     *
     * @return void
     */
    final protected function handle(): void
    {
        $this->pageGenerationService->run(maxParallel: $this->maxPublishCount, listener: $this, withData: false);
    }

    /**
     * ページ生成を開始する
     *
     * @return void
     */
    abstract public function run(): void;

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    abstract protected function writeContents(string $path, string $data): void;


    /**
    * @param \Throwable $th
    * @param string $url
    * @param int $statusCode
    * @return void
    */
    abstract protected function handleError(\Throwable $th, string $url, int $statusCode): void;

    /**
     * リクエスト前の処理
     *
     * @param \Acms\Services\PageGeneration\Entities\Page $page
     * @return void
     */
    protected function onBeforeRequest(Page $page): void
    {
    }

    /**
     * リクエスト成功時の処理
     *
     * @param \Acms\Services\PageGeneration\Entities\Page $page
     * @param string $data
     * @param int $statusCode
     * @return void
     */
    protected function onSuccess(Page $page, string $data, int $statusCode): void
    {
    }

    /**
     * ページを追加する
     *
     * @param string $url
     * @param string $destinationPathname
     * @return void
     */
    public function addPage(string $url, string $destinationPathname): void
    {
        $this->pageGenerationService->addPage($url, $destinationPathname);
    }

    /**
     * エントリーページを追加する
     *
     * @param string $url
     * @param string $destinationPathname
     * @param int $entryId
     * @return void
     */
    public function addEntryPage(string $url, string $destinationPathname, int $entryId): void
    {
        $this->pageGenerationService->addEntryPage($url, $destinationPathname, $entryId);
    }

    /**
     * 停止を要求する
     *
     * @return void
     */
    public function requestStop(): void
    {
        $this->pageGenerationService->requestStop();
    }

    /**
     * 開始時の処理
     *
     * @param Page $page
     * @return bool
     */
    public function onPageGenerationStart(Page $page): bool
    {
        $this->logger->processing($page->getDestinationPathname());
        $this->onBeforeRequest($page);
        return true;
    }

    /**
     * ページ生成成功時の処理
     *
     * @param Page $page
     * @param string $stdout
     * @param int $statusCode
     * @param ProcessResult $result
     * @return void
     */
    public function onPageGenerationSuccess(Page $page, string $stdout, int $statusCode, ProcessResult $result): void
    {
        $html = $stdout;
        if ($html !== '') {
            $html = $this->compiler->compile($html);
        }
        if ($statusCode === 200 && $html !== '') {
            $this->onSuccess($page, $html, $statusCode);
            $this->writeContents($page->getDestinationPathname(), $html);
            return;
        }
        // エラー処理
        // 200ステータスで、空文字列の場合はエラーとして扱わない
        if (!($statusCode === 200 && $html === '')) {
            $this->handleError(
                new \RuntimeException(sprintf(
                    'Console script failed (exit=%d, status=%d): %s',
                    $result->getExitCode(),
                    $statusCode,
                    trim($result->getStderr())
                )),
                $page->getUrl(),
                $statusCode
            );
        }
    }

    /**
     * ページ生成エラー時の処理
     *
     * @param Page $page
     * @param int $statusCode
     * @param ProcessResult $result
     * @return void
     */
    public function onPageGenerationError(Page $page, int $statusCode, ProcessResult $result): void
    {
        $this->handleError(
            new \RuntimeException(sprintf(
                'Console script failed (exit=%d, status=%d): %s',
                $result->getExitCode(),
                $statusCode,
                trim($result->getStderr())
            )),
            $page->getUrl(),
            $statusCode
        );
    }
}
