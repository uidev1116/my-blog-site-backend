<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Unit_Fetch extends ACMS_GET_Unit
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $eid = (int) $this->Post->get('eid', EID);
        if ($eid < 1) {
            throw new \RuntimeException('Entry ID must be greater than 0');
        }

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-front');

        $collection = $unitRepository->loadUnits($eid);
        if (count($collection) === 0) {
            return $tpl->get();
        }
        $unitRenderingService->render($collection, $tpl, $eid);
        return $tpl->get();
    }
}
