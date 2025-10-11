<?php

namespace Acms\Services\Template\Twig;

class GetModuleDataHolder
{
    /**
     * モジュールデータ
     *
     * @var array
     */
    protected $moduleData = [];

    /**
     * モジュールデータを追加
     *
     * @param string $name
     * @param array $data
     * @param string|null $identifier
     * @return void
     */
    public function addModuleData(string $name, array $data, ?string $identifier = null): void
    {
        $this->moduleData[] = [
            'title' => $name,
            'subtitle' => $identifier ?? '',
            'data' => $data,
        ];
    }

    /**
     * モジュールデータを取得
     *
     * @return array
     */
    public function getModuleData(): array
    {
        if (!isDebugMode()) {
            return [];
        }
        return $this->moduleData;
    }

    /**
     * モジュールデータを埋め込むためのスクリプトを返す
     *
     * @return string
     */
    public function embeddedJson(): string
    {
        if (!isDebugMode()) {
            return '';
        }
        if (!sessionWithAdministration()) {
            return '';
        }
        $data = [
            'title' => 'V2モジュール変数',
            'enTitle' => 'V2 Module vars',
            'items' => $this->moduleData,
        ];
        $json = json_encode($data);
        if ($json === false) {
            return '';
        }
        return "<div id=\"acms-module-data\" data-json=\"" . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . "\"></div>\n";
    }
}
