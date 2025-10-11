<?php

use Acms\Services\Facades\Application;
use Acms\Services\Unit\Contracts\ImageUnit;

class ACMS_GET_Admin_Unit_Render extends ACMS_GET
{
    public function get()
    {
        $unitId = $this->Get->get('id');
        $type = $this->Get->get('type');

        if ($unitId === '') {
            return '';
        }

        if ($type === '') {
            return '';
        }

        /** @var \Acms\Services\Unit\Repository $unitService */
        $unitService = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Edit $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-edit');

        $unit = $unitService->makeModel($type);
        if (is_null($unit)) {
            return '';
        }
        $unit->setId($unitId);

        $request = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $unit->extract($request);

        if ($unit instanceof ImageUnit && $unit->canBePrimaryImage()) {
            $requestKey = 'primary_image_' . $unit->getId(); // フロントエンドの都合でsuffixとして、ユニットIDが付与されている。
            if (isset($request[$requestKey]) && $request[$requestKey] !== '') {
                $isPrimaryImage = $request[$requestKey] === $unit->getId();
                $unit->setIsPrimaryImage($isPrimaryImage);
            }
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $unitRenderingService->renderEdit($unit, $tpl, []);

        return $tpl->get();
    }
}
