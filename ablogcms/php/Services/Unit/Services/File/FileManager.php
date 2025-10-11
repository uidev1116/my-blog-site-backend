<?php

namespace Acms\Services\Unit\Services\File;

use Acms\Services\Facades\Entry;
use ACMS_POST_File;
use ACMS_Hook;

/**
 * @phpstan-import-type FileData from FileDataExtractor
 * @phpstan-import-type MultiLangFileData from FileDataExtractor
 * @phpstan-import-type SingleFileData from FileDataExtractor
 *
 * @phpstan-type FileProcessResult array{
 *  path: string,
 *  edit: 'delete'|''
 * }
 */
class FileManager
{
    use \Acms\Traits\Unit\UnitMultiLangTrait;
    use \Acms\Traits\Common\AssetsTrait;

    /**
     * @var ACMS_POST_File
     */
    private $fileHelper;

    /**
     * @var \Acms\Services\Common\HookFactory
     */
    private $hook;

    /**
     * コンストラクタ
     * @param bool $removeOld 古いファイルを削除するかどうか
     */
    public function __construct(bool $removeOld = true)
    {
        $this->hook = ACMS_Hook::singleton();
        $this->fileHelper = new ACMS_POST_File($removeOld);
    }

    /**
     * 抽出されたデータからファイルを処理
     *
     * @param FileData $data
     * @return FileProcessResult[]
     */
    public function processFiles(array $data): array
    {
        if ($data['type'] === 'multilang') {
            /** @var MultiLangFileData $data */
            return $this->processMultiLangFiles($data);
        } else {
            /** @var SingleFileData $data */
            return $this->processSingleFile($data);
        }
    }

    /**
     * 多言語ファイルの処理
     *
     * @param MultiLangFileData $data
     * @return FileProcessResult[]
     */
    private function processMultiLangFiles($data): array
    {
        $results = [];

        foreach ($data['file_requests'] as $request) {
            $result = [
                'path' => $request['old_path'],
                'edit' => $request['edit']
            ];

            $oldPath = $this->validateRemovePath('file', $request['old_path']) ? $request['old_path'] : '';

            $filepath = $this->fileHelper->buildAndSave(
                $oldPath,
                $request['tmp_name'],
                $request['file_name'],
                $request['edit']
            );

            if ($filepath !== '') {
                // $filepath が空文字は以下のパターン
                // 1. ファイルすでにアップロード済み
                // 2. 削除モードにより削除済み
                $result['path'] = $filepath;
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * 単一ファイルの処理
     *
     * @param SingleFileData $data
     * @return FileProcessResult[]
     */
    private function processSingleFile(array $data): array
    {
        $request = $data['file_request'];
        $result = [
            'path' => $request['old_path'],
            'edit' => $request['edit']
        ];

        $oldPath = $this->validateRemovePath('file', $request['old_path']) ? $request['old_path'] : '';

        $filepath = $this->fileHelper->buildAndSave(
            $oldPath,
            $request['tmp_name'],
            $request['file_name'],
            $request['edit']
        );

        if ($filepath !== '') {
            $result['path'] = $filepath;
        }

        return [$result];
    }

    /**
     * エントリーの新バージョン用にファイルを複製
     *
     * @param array $filePaths
     * @return string
     */
    public function duplicateFilesForNewVersion(array $filePaths): string
    {
        if (!Entry::isNewVersion()) {
            throw new \LogicException('ファイルの複製は新バージョンのエントリーでのみ実行できます');
        }

        $oldPaths = $this->explodeUnitDataTrait($this->implodeUnitDataTrait($filePaths));
        $newPaths = [];

        foreach ($oldPaths as $oldPath) {
            if (in_array($oldPath, Entry::getUploadedFiles(), true)) {
                $newPaths[] = $oldPath;
                continue;
            }

            $newPath = $this->createUniqueFilepathTrait($oldPath);
            $this->copyFile($oldPath, $newPath);
            $newPaths[] = $newPath;
        }

        return $this->implodeUnitDataTrait($newPaths);
    }

    /**
     * ファイルをコピーしてフックを実行
     *
     * @param string $oldPath
     * @param string $newPath
     * @return void
     */
    private function copyFile(string $oldPath, string $newPath): void
    {
        $path = ARCHIVES_DIR . $oldPath;
        $newFullPath = ARCHIVES_DIR . $newPath;

        copyFile($path, $newFullPath, true);

        if (HOOK_ENABLE) {
            $this->hook->call('mediaCreate', $newFullPath);
        }
    }
}
