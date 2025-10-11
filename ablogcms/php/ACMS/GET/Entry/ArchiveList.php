<?php

use Acms\Modules\Get\Helpers\Entry\ArchiveListHelper;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database;

class ACMS_GET_Entry_ArchiveList extends ACMS_GET_Entry
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    public $_axis = [ // phpcs:ignore
        'bid'   => 'self',
        'cid'   => 'self',
    ];

    /**
     * コンフィグの取得
     *
     * @return array{
     *   order: string,
     *   limit: int,
     *   scope: string,
     * }
     */
    protected function initConfig(): array
    {
        return [
            'order' => config('entry_archive_list_order'),
            'limit' => (int) config('entry_archive_list_limit'),
            'scope' => config('entry_archive_list_chunk'),
        ];
    }

    public function get()
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }

            $tpl = new Template($this->tpl, new ACMS_Corrector());
            TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);
            $archiveListHelper = new ArchiveListHelper($this->getBaseParams([]));

            $substr = $archiveListHelper->getArchiveScope($this->config['scope']);
            if ($this->config['scope'] === 'biz_year') {
                $date = $this->Q->getArray('date');
                $biz_year = isset($date[0]) && $date[0] ? $date[0] : date('Y');
                if (isset($date[1]) && $date[1]) {
                    if ($time = strtotime($date[0] . '-' . $date[1] . '-01 -3month')) {
                        $biz_year = date('Y', $time);
                    }
                }
                $this->start = $biz_year++ . '-04-01 00:00:00';
                $this->end = $biz_year . '-03-31 23:59:59';
                $this->config['limit'] = 12;
            }
            $sql = $archiveListHelper->buildEntryArchiveListQuery($this->config['order'], $this->config['limit'], $substr);
            $q = $sql->get(dsn());
            $data = Database::query($q, 'all');
            $outputData = $archiveListHelper->buildOutputData($data, $this->config['scope']);

            foreach ($outputData as $row) {
                $vars = [
                    'amount' => $row['amount'] ?? null,
                    'chunkDate' => $row['date'],
                    'url' => $row['url'],
                ];
                $vars += TemplateHelper::buildDate(date('Y-m-d H:i:s', strtotime($row['date'])), $tpl, 'archive:loop');
                $tpl->add('archive:loop', $vars);
            }
            return $tpl->get();
        } catch (Exception $e) {
            return '';
        }
    }
}
