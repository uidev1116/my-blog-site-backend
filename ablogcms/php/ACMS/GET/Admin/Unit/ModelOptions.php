<?php

declare(strict_types=1);

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Unit_ModelOptions extends ACMS_GET
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        return $tpl->render([
            'options' => $this->getOptions()
        ]);
    }

    /**
     * ユニット設定用のオプションを取得する
     *
     * @see \Acms\Services\Unit\Registry::getOptions()
     * @return array{
     *   value: string,
     *   label: string
     * }[]
     */
    protected function getOptions(): array
    {
        $registory = Application::make('unit-registry');
        assert($registory instanceof \Acms\Services\Unit\Registry);
        $options = $registory->getOptions();
        $options = array_filter(
            $options,
            function ($option) {
                return $option['value'] !== 'youtube'; // youbute は非推奨なので除外
            }
        );
        $options = array_values($options); // 添字の振り直し
        return $options;
    }
}
