<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Unit\UnitCollection;

class ACMS_GET_Entry_Continue extends ACMS_GET_Entry
{
    use \Acms\Traits\Modules\ConfigTrait;

    public $_axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    public $_scope = [
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'field' => 'global',
        'date' => 'global',
        'start' => 'global',
        'end' => 'global',
        'page' => 'global',
    ];

    /**
     * コンフィグの取得
     *
     * @return array{
     *   customField: bool,
     * }
     */
    protected function initConfig(): array
    {
        return [
            'customField' => config('entry_continue_field') === 'on',
        ];
    }

    public function get()
    {
        try {
            if (!$this->eid) {
                throw new RuntimeException('Not found entry id.');
            }
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }

            $DB = DB::singleton(dsn());
            $Tpl = new Template($this->tpl, new ACMS_Corrector());
            TemplateHelper::buildModuleField($Tpl);

            $SQL = SQL::newSelect('entry');
            $SQL->addWhereOpr('entry_id', $this->eid);

            $q = $SQL->get(dsn());
            if (!$row = $DB->query($q, 'row')) {
                $Tpl->add('notFound');
                return $Tpl->get();
            }

            $bid = $row['entry_blog_id'];
            $cid = $row['entry_category_id'];
            $eid = $row['entry_id'];
            $link = $row['entry_link'];
            $inheritUrl = acmsLink([
                'eid' => $eid,
            ]);

            $vars = [];

            /**  @var \Acms\Services\Unit\Repository $unitService */
            $unitService = Application::make('unit-repository');
            /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
            $unitRenderingService = Application::make('unit-rendering-front');

            //-------
            // unit
            $collection = $unitService->loadUnits($eid);
            if (count($collection) > 0) {
                $displayUnitCollection = $collection->slice(0, (int) ($row['entry_summary_range'] ?? 0));
                if (count($displayUnitCollection) > 0) {
                    $unitRenderingService->render($displayUnitCollection, $Tpl, $eid);
                }
            }

            //-------
            // field
            if ($this->config['customField']) {
                $vars += TemplateHelper::buildField(loadEntryField($this->eid), $Tpl, [], 'entry');
            }

            $vars += [
                'status' => $row['entry_status'],
                'url' => $link !== '' ? $link : $inheritUrl,
                'title' => addPrefixEntryTitle(
                    $row['entry_title'],
                    $row['entry_status'],
                    $row['entry_start_datetime'],
                    $row['entry_end_datetime'],
                    $row['entry_approval']
                ),
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
            ];

            //------
            // date
            $vars += TemplateHelper::buildDate($row['entry_datetime'], $Tpl, []);
            $vars += TemplateHelper::buildDate($row['entry_updated_datetime'], $Tpl, [], 'udate#');
            $vars += TemplateHelper::buildDate($row['entry_posted_datetime'], $Tpl, [], 'pdate#');

            $Tpl->add(null, $vars);

            return $Tpl->get();
        } catch (Exception $e) {
            return '';
        }
    }
}
