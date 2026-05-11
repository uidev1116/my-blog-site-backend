<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;

class TemplateGenerator extends Generator
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $path
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    protected function getName(): string
    {
        return '部分テンプレートの書き出し' . '( ' . $this->path . ' )';
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (!$this->path) {
            throw new \RuntimeException('no selected path.');
        }
        $url = acmsLink(['bid' => BID], false) . $this->path;
        $this->addPage($url, $this->path);
        $this->logger->start($this->getName(), 1);
        $this->handle();
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    protected function writeContents(string $path, string $data): void
    {
        $destPath = $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $path;
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
