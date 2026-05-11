<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;

class CategoryGenerator extends Generator
{
    /**
     * @var int[]
     */
    protected $categoryIds = [];

    /**
     * @param int[] $categoryIds
     * @return void
     */
    public function setCategoryIds(array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
    }

    protected function getName(): string
    {
        return "カテゴリートップの書き出し（{$this->targetBlogName}）";
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        array_map(
            function (int $categoryId) {
                $blogUrl = acmsLink(['bid' => $this->targetBlogId]);
                $url = acmsLink([
                    'bid' => $this->targetBlogId,
                    'cid' => $categoryId,
                ]);
                $categoryDir = substr($url, strlen($blogUrl));
                $filepath = $categoryDir . 'index.html';

                if ($url) {
                    $this->addPage($url, $filepath);
                }
            },
            $this->categoryIds
        );
        $this->logger->start($this->getName(), count($this->categoryIds));
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
}
