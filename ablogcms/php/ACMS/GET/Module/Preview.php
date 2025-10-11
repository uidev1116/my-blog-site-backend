<?php

use Acms\Services\Facades\Database as DB;

class ACMS_GET_Module_Preview extends ACMS_GET_Layout
{
    public function get()
    {
        $tplEngine = new Template($this->tpl, new ACMS_Corrector());

        $mid  = (int)$this->Get->get('mid');
        $tpl = $this->Get->get('tpl');
        if ($mid <= 0) {
            return $tplEngine->get();
        }
        $module = $this->findModule($mid);
        if (is_null($module)) {
            return $tplEngine->get();
        }


        $html = $this->createPreviewHtml($module, $tpl);
        $tplEngine->add(null, [
            'html'  => $html,
        ]);

        return $tplEngine->get();
    }

    /**
     * 指定したIDのモジュールを取得する
     *
     * @param int $mid モジュールID
     *
     * @return array{module_id: int, module_identifier: string, module_name: string}|null モジュール
     */
    protected function findModule(int $mid): ?array
    {
        $sql = SQL::newSelect('module');
        $sql->addSelect('module_id');
        $sql->addSelect('module_identifier');
        $sql->addSelect('module_name');
        $sql->addWhereOpr('module_id', $mid);
        /** @var array{module_id: int, module_identifier: string, module_name: string}|false $module */
        $module = DB::query($sql->get(dsn()), 'row');
        return is_array($module) ? $module : null;
    }

    /**
     * モジュールのプレビューHTMLを生成する
     *
     * @param array{module_id: int, module_identifier: string, module_name: string} $module モジュール
     * @param string $tpl テンプレートパス
     *
     * @return string モジュールのプレビューHTML
     */
    protected function createPreviewHtml(array $module, string $tpl): string
    {
        $html = $this->spreadModule($module['module_name'], $module['module_identifier'], $tpl);
        return buildIF($html);
    }
}
