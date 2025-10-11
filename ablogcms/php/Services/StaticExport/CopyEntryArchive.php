<?php

namespace Acms\Services\StaticExport;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Application;

class CopyEntryArchive
{
    /**
     * @var array
     */
    protected $destinationPaths;

    /**
     * @var \Acms\Services\Unit\Repository
     */
    protected $unitRepository;

    /**
     * CopyEntryArchive constructor.
     * @param array $destinationPaths
     */
    public function __construct($destinationPaths)
    {
        $this->destinationPaths = $destinationPaths;
        $this->unitRepository = Application::make('unit-repository');
        assert($this->unitRepository instanceof \Acms\Services\Unit\Repository);
    }

    /**
     * @param int $eid
     */
    public function copy($eid)
    {
        $field = loadEntryField($eid);
        $this->copyUnitArchives($eid);
        $this->fieldDupe($field);
    }

    /**
     * @param int $eid
     * @return void
     */
    protected function copyUnitArchives($eid)
    {
        $collection = $this->unitRepository->loadUnits($eid);

        foreach ($collection->flat() as $unit) {
            if ($unit instanceof \Acms\Services\Unit\Contracts\StaticExport) {
                $paths = $unit->outputAssetPaths();
                foreach ($paths as $path) {
                    $this->allCopy($path);
                }
            }
            if ($unit::getUnitType() === 'custom') {
                $field = acmsDangerUnserialize($unit->getField6());
                $this->fieldDupe($field);
            }
        }
    }

    /**
     * @param \Field $Field
     */
    protected function fieldDupe($Field)
    {
        foreach ($Field->listFields() as $fd) {
            if (preg_match('/(.*?)@path$/', $fd, $match)) {
                $_fd = $match[1];

                // カスタムフィールドグループ対応
                $ary_path = $Field->getArray($_fd . '@path');
                if (is_array($ary_path) && count($ary_path) > 0) {
                    $fieldIndex = 0;
                    foreach ($ary_path as $path) {
                        if (
                            1
                            and LocalStorage::isFile(ARCHIVES_DIR . $path)
                            and preg_match('@^(.*?)([^/]+)(\.[^.]+)$@', $path, $match)
                        ) {
                            foreach (
                                [
                                    '' => '@path',
                                    'large-' => '@largePath',
                                    'tiny-' => '@tinyPath',
                                    'square-' => '@squarePath',
                                ] as $pfx => $name
                            ) {
                                if (
                                    1
                                    and $path = $Field->get($_fd . $name, null, $fieldIndex)
                                    and LocalStorage::isFile(ARCHIVES_DIR . $path)
                                ) {
                                    $this->allCopy(ARCHIVES_DIR . $path);
                                }
                            }
                            $fieldIndex++;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $path
     */
    protected function allCopy($path)
    {
        foreach ($this->destinationPaths as $destinationPath) {
            LocalStorage::makeDirectory(dirname($destinationPath . $path));
            LocalStorage::copy($path, $destinationPath . $path);

            if ($dirname = dirname($path)) {
                $dirname .= '/';
            }
            $basename = LocalStorage::mbBasename($path);
            $files = glob($dirname . '*-' . $basename);
            if (is_array($files)) {
                foreach ($files as $file) {
                    LocalStorage::copy($file, $destinationPath . $file);
                }
            }
        }
    }
}
