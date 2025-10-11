<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use Symfony\Component\Finder\Finder;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

class ThemeGenerator extends Generator
{
    /**
     * @var string
     */
    protected $sourceTheme;

    /**
     * @var string[]
     */
    protected $exclusionList = [];

    /**
     * @param string $sourceTheme
     * @return void
     */
    public function setSourceTheme(string $sourceTheme): void
    {
        if ($sourceTheme === '') {
            throw new \InvalidArgumentException('source theme is empty.');
        }
        $this->sourceTheme = $sourceTheme;
    }

    protected function getName(): string
    {
        return 'テンプレートの書き出し ( ' . $this->sourceTheme . ' )';
    }

    /**
     * @param string[] $list
     * @return void
     */
    public function setExclusionList(array $list): void
    {
        $this->exclusionList = $list;
    }

    /**
     * @inheritDoc
     */
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                if (!$this->sourceTheme) {
                    $reject(new \RuntimeException('no selected source theme.'));
                    return;
                }
                $finder = new Finder();
                $iterator = $finder
                ->in($this->sourceTheme)
                ->notPath('include')
                ->notPath('admin')
                ->name('/\.(html|htm|json)$/');

                if (config('forbid_direct_access_tpl') !== 'off') {
                    $iterator->notPath(config('forbid_direct_access_tpl'));
                    $iterator->notName(config('forbid_direct_access_tpl'));
                }
                foreach ($this->exclusionList as $path) {
                    if (!empty($path)) {
                        $iterator->notPath($path);
                    }
                }
                $iterator->files();

                $pages = $this->createPages($iterator);
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
        $baseDir = $this->destination->getDestinationPath() . $this->destination->getBlogCode();
        $destPath = $baseDir . $path;
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

    /**
     * @param \Symfony\Component\Finder\Finder $iterator
     * @return Page[]
     */
    protected function createPages(\Symfony\Component\Finder\Finder $iterator): array
    {
        return array_map(
            function (\Symfony\Component\Finder\SplFileInfo $file) {
                $pathname = $file->getRelativePathname();
                $url = acmsLink(['bid' => BID], false) . $pathname;
                return new Page($url, $pathname);
            },
            iterator_to_array($iterator, false)
        );
    }
}
