<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;

class PageGenerator extends Generator
{
    /**
     * @var int|null
     */
    protected $maxPage;

    /**
     * @param int $maxPage
     * @return void
     */
    public function setMaxPage(int $maxPage): void
    {
        if ($maxPage < 2) {
            throw new \InvalidArgumentException('Invalid max page. Prease set more than 2.');
        }
        $this->maxPage = $maxPage;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (is_null($this->maxPage)) {
            throw new \RuntimeException('no selected max page.');
        }
        if ($this->maxPage < 2) {
            throw new \RuntimeException('max page is less than 2.');
        }

        $pages = range(2, $this->maxPage);

        array_map(
            function (int $page) {
                $url = acmsLink([
                    'bid' => $this->targetBlogId,
                    'page' => $page,
                ]);
                $filename = 'page' . $page . '.html';

                if ($url) {
                    $this->addPage($url, $filename);
                }
            },
            $pages
        );
        $this->logger->start("2ページ以降を生成（{$this->targetBlogName}）", count($pages));
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
            LocalStorage::makeDirectory($destination);
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
        // ページネーションの生成は1度404が返ってきたら、それ以降のページは404が返ってくるため、次のページの生成を中止する
        $this->requestStop();
        $this->logger->error($th->getMessage(), $url, $statusCode);
    }
}
