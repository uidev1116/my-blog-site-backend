<?php

namespace Acms\Traits\Common;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Unit\UnitCollection;
use SQL;
use Field;
use ACMS_Hook;

trait AssetsTrait
{
    /**
     * カスタムフィールド削除時に実態ファイルも削除する
     *
     * @param \Field $field
     * @return void
     */
    public function removeFieldAssetsTrait(Field $field): void
    {
        foreach ($field->listFields() as $fd) {
            if (
                !strpos($fd, '@path') &&
                !strpos($fd, '@tinyPath') &&
                !strpos($fd, '@largePath') &&
                !strpos($fd, '@squarePath')
            ) {
                continue;
            }
            foreach ($field->getArray($fd, true) as $old) {
                $path = ARCHIVES_DIR . $old;
                deleteFile($path, true);
                deleteFile("{$path}.webp", true);
                if (HOOK_ENABLE) {
                    $Hook = ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $path);
                }
            }
        }
    }

    /**
     * カスタムフィールド削除時に実態ファイルも削除する
     *
     * @param string[] $filePaths
     * @return void
     */
    public function removeFileAssetsTrait(array $filePaths): void
    {
        foreach ($filePaths as $path) {
            deleteFile(ARCHIVES_DIR . $path, true);
            deleteFile("{$path}.webp", true);
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaDelete', $path);
            }
        }
    }

    /**
     * カスタムフィールド削除時に実態ファイルも削除する
     *
     * @param string[] $imagePaths
     * @return void
     */
    public function removeImageAssetsTrait(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            $normal = ARCHIVES_DIR . $path;
            $large = otherSizeImagePath($normal, 'large');
            $tiny = otherSizeImagePath($normal, 'tiny');
            $square = otherSizeImagePath($normal, 'square');
            deleteFile($normal, true);
            deleteFile($large, true);
            deleteFile($tiny, true);
            deleteFile($square, true);
            deleteFile("{$normal}.webp", true);
            deleteFile("{$large}.webp", true);
            deleteFile("{$tiny}.webp", true);
            deleteFile("{$square}.webp", true);
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaDelete', $normal);
                $Hook->call('mediaDelete', $large);
                $Hook->call('mediaDelete', $tiny);
                $Hook->call('mediaDelete', $square);
            }
        }
    }

    /**
     * 画像パスから新しいファイルを作成して、新しいパスのリストを返却
     *
     * @param string[] $imagePaths
     * @return array
     */
    public function duplicateImagesTrait(array $imagePaths): array
    {
        $newImagePaths = [];
        $hook = HOOK_ENABLE ? ACMS_Hook::singleton() : null;

        foreach ($imagePaths as $imagePath) {
            $fullpath = ARCHIVES_DIR . $imagePath;
            $newFullpath = $this->createUniqueFilepathTrait($fullpath);

            $largeFullpath = otherSizeImagePath($fullpath, 'large');
            $tinyFullpath = otherSizeImagePath($fullpath, 'tiny');
            $squareFullpath = otherSizeImagePath($fullpath, 'square');

            $newLargeFullpath = otherSizeImagePath($newFullpath, 'large');
            $newTinyFullpath = otherSizeImagePath($newFullpath, 'tiny');
            $newSquareFullpath = otherSizeImagePath($newFullpath, 'square');
            if (PublicStorage::isReadable($fullpath)) {
                copyFile($fullpath, $newFullpath, true);
                if ($hook) {
                    $hook->call('mediaCreate', $newFullpath);
                }
            }
            if (PublicStorage::isReadable($largeFullpath)) {
                copyFile($largeFullpath, $newLargeFullpath, true);
                if ($hook) {
                    $hook->call('mediaCreate', $newLargeFullpath);
                }
            }
            if (PublicStorage::isReadable($tinyFullpath)) {
                copyFile($tinyFullpath, $newTinyFullpath, true);
                if ($hook) {
                    $hook->call('mediaCreate', $newTinyFullpath);
                }
            }
            if (PublicStorage::isReadable($squareFullpath)) {
                copyFile($squareFullpath, $newSquareFullpath, true);
                if ($hook) {
                    $hook->call('mediaCreate', $newSquareFullpath);
                }
            }
            $newImagePaths[] = substr($newFullpath, strlen(ARCHIVES_DIR));
        }
        return $newImagePaths;
    }

    /**
     * ファイルパスから新しいファイルを作成して、新しいパスのリストを返却
     *
     * @param string[] $filePaths
     * @return array
     */
    public function duplicateFilesTrait(array $filePaths): array
    {
        $newFilePaths = [];
        $hook = HOOK_ENABLE ? ACMS_Hook::singleton() : null;

        foreach ($filePaths as $filePath) {
            $fullpath = ARCHIVES_DIR . $filePath;
            $newFullpath = $this->createUniqueFilepathTrait($fullpath);
            if (PublicStorage::isReadable($fullpath)) {
                copyFile($fullpath, $newFullpath, true);
                if ($hook) {
                    $hook->call('mediaCreate', $newFullpath);
                }
            }
            $newFilePaths[] = substr($newFullpath, strlen(ARCHIVES_DIR));
        }
        return $newFilePaths;
    }

    /**
     * カスタムフィールド複製時に実態ファイルも複製する
     *
     * @param \Field $field
     * @return void
     */
    public function duplicateFieldsTrait(Field $field): void
    {
        $hook = HOOK_ENABLE ? ACMS_Hook::singleton() : null;

        foreach ($field->listFields() as $fd) {
            if (preg_match('/(.*?)@path$/', $fd, $match)) {
                $fieldBase = $match[1];
                $set = false;
                foreach ($field->getArray("{$fieldBase}@path") as $i => $path) {
                    $fullpath = ARCHIVES_DIR . $path;
                    if (!PublicStorage::isFile($fullpath)) {
                        if ($i === 0) {
                            $field->deleteField("{$fieldBase}@path");
                            $field->deleteField("{$fieldBase}@largePath");
                            $field->deleteField("{$fieldBase}@tinyPath");
                            $field->deleteField("{$fieldBase}@squarePath");
                        }
                        $field->addField("{$fieldBase}@path", '');
                        $field->addField("{$fieldBase}@largePath", '');
                        $field->addField("{$fieldBase}@tinyPath", '');
                        $field->addField("{$fieldBase}@squarePath", '');

                        continue;
                    }
                    if (!$set) {
                        $field->delete("{$fieldBase}@path");
                        $field->delete("{$fieldBase}@largePath");
                        $field->delete("{$fieldBase}@tinyPath");
                        $field->delete("{$fieldBase}@squarePath");
                        $set = true;
                    }
                    $info = pathinfo($path);
                    $dirname = $info['dirname'] === '' ? '' : $info['dirname'] . '/';
                    PublicStorage::makeDirectory(ARCHIVES_DIR . $dirname);

                    $largeFullpath = otherSizeImagePath($fullpath, 'large');
                    $tinyFullpath = otherSizeImagePath($fullpath, 'tiny');
                    $squareFullpath = otherSizeImagePath($fullpath, 'square');

                    $newFullpath = $this->createUniqueFilepathTrait($fullpath);
                    $newLargeFullpath = otherSizeImagePath($newFullpath, 'large');
                    $newTinyFullpath = otherSizeImagePath($newFullpath, 'tiny');
                    $newSquareFullpath = otherSizeImagePath($newFullpath, 'square');

                    if (PublicStorage::isReadable($fullpath)) {
                        copyFile($fullpath, $newFullpath, true);
                        if ($hook) {
                            $hook->call('mediaCreate', $newFullpath);
                        }
                        $newPath = substr($newFullpath, strlen(ARCHIVES_DIR));
                        $field->add("{$fieldBase}@path", $newPath);
                    }
                    if (PublicStorage::isReadable($largeFullpath)) {
                        copyFile($largeFullpath, $newLargeFullpath, true);
                        if ($hook) {
                            $hook->call('mediaCreate', $newLargeFullpath);
                        }
                        $newLargePath = substr($newLargeFullpath, strlen(ARCHIVES_DIR));
                        $field->add("{$fieldBase}@largePath", $newLargePath);
                    }
                    if (PublicStorage::isReadable($tinyFullpath)) {
                        copyFile($tinyFullpath, $newTinyFullpath, true);
                        if ($hook) {
                            $hook->call('mediaCreate', $newTinyFullpath);
                        }
                        $newTinyPath = substr($newTinyFullpath, strlen(ARCHIVES_DIR));
                        $field->add("{$fieldBase}@tinyPath", $newTinyPath);
                    }
                    if (PublicStorage::isReadable($squareFullpath)) {
                        copyFile($squareFullpath, $newSquareFullpath, true);
                        if ($hook) {
                            $hook->call('mediaCreate', $newSquareFullpath);
                        }
                        $newSquarePath = substr($newSquareFullpath, strlen(ARCHIVES_DIR));
                        $field->add("{$fieldBase}@squarePath", $newSquarePath);
                    }
                }
            }
        }
    }

    /**
     * ユニットの削除指定されたパスがDBに存在するかチェック
     *
     * @param string $type
     * @param string $path
     * @return bool
     */
    public function validateRemovePath(string $type, string $path): bool
    {
        /** @var \Acms\Services\Unit\UnitCollection|null $oldUnitCollection */
        static $oldUnitCollection = null;
        if ($oldUnitCollection === null) {
            $oldUnitCollection = new UnitCollection([]);
        }

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');

        if (count($oldUnitCollection) === 0) {
            $unitIds = [];
            if (is_array($_POST['unit_type'])) {
                foreach (array_keys($_POST['unit_type']) as $i) {
                    $unitIds[] = (string) $_POST['unit_id'][$i];
                }
            }
            $unitData = [];
            foreach (['column', 'column_rev'] as $table) {
                $sql = SQL::newSelect($table);
                $sql->addWhereIn('column_id', $unitIds);
                $q = $sql->get(dsn());
                $data = Database::query($q, 'all');
                if ($data) {
                    $unitData = array_merge($data, $unitData);
                }
            }
            $oldUnitCollection = $unitRepository->loadModels($unitData);
        }
        foreach ($oldUnitCollection->flat() as $unit) {
            if ($unit::getUnitType() === $type && $unit instanceof \Acms\Services\Unit\Contracts\AssetProvider) {
                $paths = $unit->getFilePaths();
                if (in_array($path, $paths, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 複製時に衝突しないファイル名を生成する
     *
     * @param string $path ファイルパス
     * @return string 衝突しないファイルパス
     */
    private function createUniqueFilepathTrait(string $path): string
    {
        if (config('entry_duplicate_random_filename') !== 'off') {
            $fileinfo = pathinfo($path);
            return $fileinfo['dirname'] . '/' . uniqueString() . '.' . $fileinfo['extension'];
        }
        return PublicStorage::uniqueFilePath($path);
    }
}
