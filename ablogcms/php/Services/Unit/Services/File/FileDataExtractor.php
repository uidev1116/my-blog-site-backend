<?php

namespace Acms\Services\Unit\Services\File;

/**
 * @phpstan-type FileRequest array{
 *   caption: string,
 *   old_path: string,
 *   edit: 'delete'|'',
 *   tmp_name: string,
 *   file_name: string,
 *   index: int
 * }
 *
 * @phpstan-type SingleFileData array{
 *   type: 'single',
 *   file_request: FileRequest,
 * }
 *
 * @phpstan-type MultiLangFileData array{
 *   type: 'multilang',
 *   file_requests: array<int, FileRequest>,
 * }
 *
 * @phpstan-type FileData SingleFileData|MultiLangFileData
 */
class FileDataExtractor
{
    /**
     * @var string
     */
    private $id;

    /**
     * コンストラクタ
     * @param string $id ユニットID
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * POSTデータからファイルユニットのデータを抽出
     *
     * @param array $request リクエストデータ
     * @return FileData
     */
    public function extract(array $request): array
    {
        $captions = $request["file_caption_{$this->id}"] ?? null;

        if (is_array($captions)) {
            // 多言語ユニット
            return $this->extractMultiLang($request);
        } else {
            // 通常ユニット
            return $this->extractSingle($request);
        }
    }

    /**
     * 多言語ユニットのデータ抽出
     *
     * @param array $request
     * @return MultiLangFileData
     */
    private function extractMultiLang(array $request): array
    {
        $fileRequests = [];

        $id = $this->id;
        $captions = $request["file_caption_{$this->id}"] ?? [];
        foreach ($captions as $i => $caption) {
            $oldPath = $request["file_old_{$id}"][$i] ?? $request["file_old_{$id}"] ?? '';
            $edit = $request["file_edit_{$id}"][$i] ?? $request['file_edit_' . $id] ?? '';
            $tmpName = $_FILES["file_file_{$id}"]['tmp_name'][$i] ?? '';
            $fileName = $_FILES["file_file_{$id}"]['name'][$i] ?? '';

            $fileRequests[] = [
                'caption' => $caption ?? '',
                'old_path' => $oldPath,
                'edit' => $edit,
                'tmp_name' => $tmpName,
                'file_name' => $fileName,
                'index' => $i
            ];
        }

        return [
            'type' => 'multilang',
            'file_requests' => $fileRequests
        ];
    }

    /**
     * 通常ユニットのデータ抽出
     *
     * @param array $request
     * @return SingleFileData
     */
    private function extractSingle(array $request): array
    {
        $id = $this->id;
        $caption = $request["file_caption_{$id}"] ?? '';
        $edit = $request["file_edit_{$id}"] ?? '';
        $tmpName = is_array($_FILES["file_file_{$id}"]['tmp_name'] ?? null)
            ? $_FILES["file_file_{$id}"]['tmp_name'][0]
            : $_FILES["file_file_{$id}"]['tmp_name'] ?? '';
        $fileName = is_array($_FILES["file_file_{$id}"]['name'] ?? null)
            ? $_FILES["file_file_{$id}"]['name'][0]
            : $_FILES["file_file_{$id}"]['name'] ?? '';
        $oldPath = $request["file_old_{$id}"] ?? '';

        return [
            'type' => 'single',
            'file_request' => [
                'caption' => $caption,
                'old_path' => $oldPath,
                'edit' => $edit,
                'tmp_name' => $tmpName,
                'file_name' => $fileName,
                'index' => 0
            ]
        ];
    }
}
