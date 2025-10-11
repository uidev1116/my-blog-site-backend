<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\CopyEntryArchive;
use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use Acms\Services\StaticExport\Entities\EntryPage;
use React\Promise\Promise;
use ACMS_RAM;
use React\Promise\PromiseInterface;

use function React\Async\await;

class EntryGenerator extends Generator
{
    /**
     * @var array
     */
    protected $entryIds = [];

    /**
     * @var \Acms\Services\StaticExport\CopyEntryArchive
     */
    protected $copyArchiveEngine;

    /**
     * @var bool
     */
    protected $withArchive = false;

    /**
     * @param int[] $entryIds
     */
    public function setEntryIds(array $entryIds)
    {
        $this->entryIds = $entryIds;
    }

    /**
     * @param bool $withArchive
     */
    public function setWithArchive(bool $withArchive): void
    {
        $this->withArchive = $withArchive;
    }

    protected function getName(): string
    {
        return 'エントリーの書き出し';
    }

    /**
     * @inheritDoc
     */
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $this->copyArchiveEngine = new CopyEntryArchive([
                    $this->destination->getDestinationPath(),
                    $this->destination->getDestinationDocumentRoot() . $this->destination->getDestinationOffsetDir()
                ]);

                $pages = array_map(
                    function (int $entryId) {
                        $url = acmsLink(['bid' => BID, 'eid' => $entryId]);
                        $blogUrl = acmsLink(['bid' => BID]);
                        $filepath = substr($url, strlen($blogUrl));

                        if (ACMS_RAM::entryCode($entryId) === '') {
                            $filepath = $filepath . 'index.html';
                        }
                        if (substr($filepath, -1) === '/') {
                            $filepath = rtrim($filepath, '/') . '.html';
                        }
                        return new EntryPage($url, $filepath, $entryId);
                    },
                    $this->entryIds
                );
                $this->logger->start($this->getName(), count($pages));
                await($this->handle($pages));
                $resolve(null);
            }
        );
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    protected function writeContents(string $path, string $data): void
    {
        $destination = $this->destination->getDestinationPath() . $this->destination->getBlogCode();
        $destPath = $destination . $path;
        try {
            LocalStorage::makeDirectory(dirname($destPath));
            LocalStorage::put($destPath, $data);
        } catch (\Exception $e) {
            $this->logger->error('データの書き込みに失敗しました。', $destPath);
        }
    }

    /**
     * @param \Throwable $th
     * @param string $url
     * @return void
     */
    protected function handleError(\Throwable $th, string $url): void
    {
        if ($th instanceof \React\Http\Message\ResponseException) {
            $response = $th->getResponse();
            $this->logger->error(
                'データの取得に失敗しました。',
                $url,
                $response->getStatusCode()
            );
            return;
        }
        $this->logger->error($th->getMessage(), $url);
    }

    /**
     * @inheritDoc
     */
    protected function onBeforeRequest(Page $page): void
    {
        if ($this->withArchive && $page instanceof EntryPage) {
            $this->copyArchiveEngine->copy($page->getEntryId());
        }
    }
}
