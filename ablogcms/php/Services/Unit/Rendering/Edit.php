<?php

namespace Acms\Services\Unit\Rendering;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Media;
use Acms\Services\Unit\UnitCollection;
use Template;

class Edit
{
    /**
     * ユニットの描画
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param Template $tpl
     * @param string[] $rootBlock
     * @return array<string, mixed>
     */
    public function render(UnitCollection $collection, Template $tpl, array $rootBlock = []): array
    {
        $unitCount = count($collection);

        if ($unitCount > 0) {
            $unitRepository = Application::make('unit-repository');
            assert($unitRepository instanceof \Acms\Services\Unit\Repository);
            $unitRepository->eagerLoadCustomUnitFields($collection);

            // メディアデータのセット
            $eagerLoadedMedia = Media::mediaEagerLoadFromUnit($collection);
            foreach ($collection->flat() as $unit) {
                if ($unit instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
                    $unit->setEagerLoadedMedia($eagerLoadedMedia);
                }
            }

            foreach ($collection->flat() as $unit) {
                // ユニット独自の描画
                $unit->renderEdit($tpl, [
                    'id' => $unit->getId(),
                ], array_merge(['column:loop'], $rootBlock));

                $tpl->add(array_merge(['column:loop'], $rootBlock), [
                    'unitId' => $unit->getId(),
                    'unitType' => $unit->getType(),
                    'unitName' => $unit->getName(),
                ]);
            }
        }

        $json = $collection->treeArray();
        $json = $this->processUnitJson($json);
        return [
            'json' => json_encode($json),
        ];
    }

    /**
     * ユニット編集時の描画
     *
     * @param Model $unit
     * @param Template $tpl
     * @param string[] $rootBlock
     * @return void
     */
    public function renderEdit(Model $unit, Template $tpl, array $rootBlock = []): void
    {
        // ユニット独自の描画
        $unit->renderEdit($tpl, [
            'id' => $unit->getId(),
        ], array_merge(['column:loop'], $rootBlock));
        $tpl->add(array_merge(['column:loop'], $rootBlock), [
            'unitId' => $unit->getId(),
            'unitType' => $unit->getType(),
            'unitName' => $unit->getName(),
        ]);
    }

    /**
     * ユニットJSONデータを処理する
     *
     * @param array<int, array<string, mixed>> $json
     * @return array<int, array<string, mixed>>
     */
    private function processUnitJson(array $json): array
    {
        $processor = Application::make('unit-json-processor');
        assert($processor instanceof \Acms\Services\Unit\Services\JsonProcessor);

        return $processor->process($json);
    }
}
