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
     * @var \Acms\Services\Entry\Export
     */
    protected $entryExportHelper;

    /**
     * CopyEntryArchive constructor.
     * @param array $destinationPaths
     */
    public function __construct($destinationPaths)
    {
        $this->destinationPaths = $destinationPaths;
        $this->entryExportHelper = Application::make('entry.export');
        assert($this->entryExportHelper instanceof \Acms\Services\Entry\Export);
    }

    /**
     * @param int $eid
     */
    public function copy($eid)
    {
        $fileList = $this->entryExportHelper->exportEntryAssets([$eid]);

        foreach ($fileList['archives'] as $path) {
            $this->allCopy(ARCHIVES_DIR . $path);
        }
        foreach ($fileList['media'] as $path) {
            $this->allCopy(MEDIA_LIBRARY_DIR . $path);
        }
        foreach ($fileList['storage'] as $path) {
            $this->allCopy(MEDIA_STORAGE_DIR . $path);
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
