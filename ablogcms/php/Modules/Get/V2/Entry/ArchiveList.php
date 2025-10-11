<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Entry\ArchiveListHelper;
use Acms\Services\Facades\Database;
use RuntimeException;

class ArchiveList extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * コンフィグの取得
     *
     * @return array{
     *   order: string,
     *   limit: int,
     *   scope: 'month' | 'day' | 'year' | 'biz_year',
     * }
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'order' => $config->get('entry_archive_list_order'),
            'limit' => $this->limit ?? (int) $config->get('entry_archive_list_limit'),
            'scope' => in_array($config->get('entry_archive_list_chunk'), ['month', 'day', 'year', 'biz_year'], true)
                ? $config->get('entry_archive_list_chunk')
                : 'month',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }
            $archiveListHelper = new ArchiveListHelper($this->getBaseParams([]));

            $substr = $archiveListHelper->getArchiveScope($this->config['scope']);
            if ($this->config['scope'] === 'biz_year') {
                $date = $this->moduleContext->getArray('date');
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

            $vars = [];
            $vars['items'] = $archiveListHelper->buildOutputData($data, $this->config['scope']);
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }
}
