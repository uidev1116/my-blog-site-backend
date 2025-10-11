<?php

namespace Acms\Modules\Get\V2\Entry;

use ACMS_RAM;
use RuntimeException;

class MoreContent extends Body
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
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
        $config = $this->loadModuleConfig();
        return [
            'customField' => $config->get('entry_continue_field') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            $this->boot();
            if (!$this->eid) {
                throw new RuntimeException('Not found entry id.');
            }
            if (!$this->entryHelper->canAccessEntry($this->eid)) {
                throw new RuntimeException('Permission denied.');
            }
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }
            $entry = ACMS_RAM::entry($this->eid);
            $bid = $entry['entry_blog_id'];
            $cid = $entry['entry_category_id'];
            $eid = $entry['entry_id'];
            $vars = [
                'status' => $entry['entry_status'],
                'url' => $entry['entry_link'] ? $entry['entry_link'] : acmsLink([
                    'eid' => $eid,
                ]),
                'title' => addPrefixEntryTitle(
                    $entry['entry_title'],
                    $entry['entry_status'],
                    $entry['entry_start_datetime'],
                    $entry['entry_end_datetime'],
                    $entry['entry_approval']
                ),
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
                'body' => $this->getUnitHtml($eid, (int) ($entry['entry_summary_range'] ?? 0)),
                'datetime' => $entry['entry_datetime'],
                'createdAt' => $entry['entry_posted_datetime'],
                'updatedAt' => $entry['entry_updated_datetime'],
            ];

            if ($this->config['customField']) {
                $vars['fields'] = $this->buildFieldTrait(loadEntryField($this->eid));
            } else {
                $vars['fields'] = null;
            }
            $vars['moduleFields'] = $this->buildModuleField();
            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 続きを読む以下のユニットのHTMLを取得
     *
     *
     * @param integer $eid
     * @return string
     */
    protected function getUnitHtml(int $eid, int $summaryRange): string
    {
        $tpl = $this->getUnitTemplate();
        $allUnitCollection = $this->entryBodyHelper->getAllUnitCollection($eid);
        $displayUnitCollection = $allUnitCollection->slice(0, $summaryRange);

        return $this->buildUnitHtml($eid, $displayUnitCollection, $tpl);
    }
}
