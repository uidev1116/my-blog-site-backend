<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Unit_Single extends ACMS_GET_Admin
{
    public function get()
    {
        if ('entry-update-unit' !== substr(ADMIN, 0, 17)) {
            httpStatusCode('400 Bad Request');
            return '';
        }
        if (!sessionWithContribution()) {
            httpStatusCode('403 Forbidden');
            return '';
        }
        /** @var int<1, max>|null $entryId */
        $entryId = EID;
        if ($entryId === null) {
            httpStatusCode('400 Bad Request');
            return '';
        }
        /** @var non-empty-string|null $unitId */
        $unitId = UTID;
        $addType = substr(ADMIN, 18); // URLからユニットタイプを取得
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Edit $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-edit');

        $collection = $unitRepository->loadUnits(
            eid: $entryId,
            options: ['setPrimaryImage' => true],
        );
        if ($addType) {
            // 新規作成
            $newUnit = $unitRepository->create($addType, $addType, 0);
            if (is_null($newUnit)) {
                // 新規作成に失敗した場合は400エラー
                httpStatusCode('400 Bad Request');
                return '';
            }
            $newUnit->setId($newUnit->generateNewIdTrait());
            // 基準となるユニットを取得
            $unit = $collection->find(fn ($unit) => $unit->getId() === $unitId);
            // 基準となるユニットの下に追加。基準となるユニットがない場合は先頭に追加。
            $position = $unit !== null ? ['index' => $unit->getSort(), 'rootId' => $unit->getParentId()] : ['index' => 1, 'rootId' => null];
            $collection->insertAt($newUnit, $position);
            $vars = array_merge($vars, [
                'id' => $newUnit->getId(),
            ]);
        } else {
            // 更新
            $unit = $collection->find(fn ($unit) => $unit->getId() === $unitId);
            if (is_null($unit)) {
                // 更新対象のユニットが存在しない場合は400エラー
                httpStatusCode('400 Bad Request');
                return '';
            }
            $vars = array_merge($vars, [
                'id' => $unit->getId(),
            ]);
        }
        $vars = array_merge($vars, $unitRenderingService->render($collection, $tpl, []));
        $tpl->add(null, $vars);

        return $tpl->get();
    }
}
