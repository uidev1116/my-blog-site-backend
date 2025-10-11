<?php

namespace Acms\Services\StaticExport\Contracts;

use Acms\Services\StaticExport\Destination;
use Acms\Services\StaticExport\Logger;
use Acms\Services\StaticExport\Compiler as PublishCompiler;
use Acms\Services\StaticExport\Entities\Page;
use React;
use React\Promise\Promise;

use function React\Async\await;
use function React\Promise\all;

abstract class Generator
{
    /**
     * @var int
     */
    private const PUBLISH_INTERVAL_SECONDS = 500000; // 0.5秒

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
     * @var \React\Http\Browser
     */
    protected $httpClient;

    /**
     * Generator constructor.
     * @param \Acms\Services\StaticExport\Compiler $compiler
     * @param \Acms\Services\StaticExport\Destination $destination
     * @param \Acms\Services\StaticExport\Logger $logger
     * @param int $maxPublishCount
     */
    public function __construct(
        PublishCompiler $compiler,
        Destination $destination,
        Logger $logger,
        int $maxPublishCount = 5,
        string $nameServer = '8.8.8.8'
    ) {
        $this->compiler = $compiler;
        $this->destination = $destination;
        $this->logger = $logger;
        if ($maxPublishCount > 0) {
            $this->maxPublishCount = $maxPublishCount;
        }
        $this->httpClient = $this->createHttpClient($nameServer);
    }

    /**
     * @param \Acms\Services\StaticExport\Entities\Page[] $pages
     * @return \React\Promise\PromiseInterface<void>
     */
    final protected function handle(array $pages): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) use ($pages) {
                $publishChunks = array_chunk($pages, $this->maxPublishCount > 0 ? $this->maxPublishCount : 5);
                foreach ($publishChunks as $publishChunk) {
                    if (!$this->shouldGenerateNextPage()) {
                        break;
                    }
                    await($this->generate($publishChunk));
                    usleep(self::PUBLISH_INTERVAL_SECONDS);
                }
                $resolve(null);
            }
        );
    }

    /**
     * @return \React\Promise\PromiseInterface<void>
     */
    abstract public function run(): \React\Promise\PromiseInterface;

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    abstract protected function writeContents(string $path, string $data): void;


    /**
    * @param \Throwable $th
    * @param string $url
    */
    abstract protected function handleError(\Throwable $th, string $url): void;

    /**
     * @param \Acms\Services\StaticExport\Entities\Page[] $pages
     * @return \React\Promise\PromiseInterface<array<void>>
     */
    private function generate(array $pages = []): \React\Promise\PromiseInterface
    {
        $promises = array_map(
            function (Page $page) {
                if ($this->logger) {
                    $this->logger->processing($page->getDestinationPathname());
                };
                $this->onBeforeRequest($page);
                return $this->request(
                    $page->getUrl(),
                    function (string $data) use ($page) {
                        $this->writeContents($page->getDestinationPathname(), $data);
                    },
                    function (\Throwable $th) use ($page) {
                        $this->handleError($th, $page->getUrl());
                    }
                );
            },
            $pages
        );
        return all($promises);
    }

    /**
     * 次のページを生成するかどうか
     * @return bool
     */
    protected function shouldGenerateNextPage(): bool
    {
        return true;
    }

    /**
     * // Do something before sending HTTP request
     * @param \Acms\Services\StaticExport\Entities\Page $page
     * @return void
     */
    protected function onBeforeRequest(Page $page): void
    {
    }

    /**
     * @param string $url
     * @param callable $onSuccess
     * @param callable $onFailure
     * @return \React\Promise\PromiseInterface<void>
     */
    final protected function request(
        string $url,
        callable $onSuccess = null,
        callable $onFailure = null
    ): \React\Promise\PromiseInterface {
        return $this->httpClient->get($url)
            ->then(
                function (\Psr\Http\Message\ResponseInterface $response) use ($onSuccess) {
                    $data = (string)$response->getBody();
                    $code = $response->getStatusCode();
                    if (!empty($data)) {
                        $data = $this->compiler->compile($data);
                    }
                    if ($onSuccess !== null) {
                        $onSuccess($data, $code);
                    }
                }
            )->catch(
                function (\Throwable $th) use ($onFailure) {
                    if ($onFailure !== null) {
                        $onFailure($th);
                    }
                }
            );
    }

    /**
     * @param string $nameServer
     * @return \React\Http\Browser
     */
    private function createHttpClient($nameServer): \React\Http\Browser
    {
        $dnsResolverFactory = new React\Dns\Resolver\Factory();
        $dnsResolver = $dnsResolverFactory->createCached($nameServer);
        $connector = new React\Socket\Connector([
            'dns' => $dnsResolver
        ]);
        return new React\Http\Browser($connector);
    }
}
