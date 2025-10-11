<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

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
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                if (!$this->path) {
                    $reject(new \RuntimeException('no selected path.'));
                    return;
                }

                $url = acmsLink(['bid' => BID], false) . $this->path;
                $pages = [new Page($url, $this->path)];
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
}
