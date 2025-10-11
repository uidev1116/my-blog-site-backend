<?php

class ACMS_GET_Admin_Module_IndexJson extends ACMS_GET_Admin_Module_Index
{
    /**
     * @inheritDoc
     */
    protected function render(\Template $tpl, array $vars): string
    {
        $json = (string)json_encode($vars);
        if (jsonValidate($json)) {
            return $json;
        }
        return '{}';
    }

    /**
     * @inheritDoc
     */
    protected function defaultOrder(): array
    {
        return [
            'field' => 'updated_datetime',
            'direction' => 'desc',
        ];
    }
}
