<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\CopyEntryArchive;
use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\PageGeneration\Entities\EntryPage;
use ACMS_RAM;

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
        return "エントリーの書き出し（{$this->targetBlogName}）";
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->copyArchiveEngine = new CopyEntryArchive([
            $this->destination->getDestinationPath(),
            $this->destination->getDestinationDocumentRoot() . $this->destination->getDestinationOffsetDir()
        ]);

        array_map(
            function (int $entryId) {
                $url = acmsLink(['bid' => $this->targetBlogId, 'eid' => $entryId]);
                $blogUrl = acmsLink(['bid' => $this->targetBlogId]);
                $filepath = substr($url, strlen($blogUrl));

                if (ACMS_RAM::entryCode($entryId) === '') {
                    $filepath = $filepath . 'index.html';
                }
                if (substr($filepath, -1) === '/') {
                    $filepath = rtrim($filepath, '/') . '.html';
                }
                if ($url) {
                    $this->addEntryPage($url, $filepath, $entryId);
                }
            },
            $this->entryIds
        );
        $this->logger->start($this->getName(), count($this->entryIds));
        $this->handle();
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
     * @param int $statusCode
     * @return void
     */
    protected function handleError(\Throwable $th, string $url, int $statusCode): void
    {
        $this->logger->error($th->getMessage(), $url, $statusCode);
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
