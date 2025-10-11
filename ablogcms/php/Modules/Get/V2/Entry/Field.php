<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use ACMS_RAM;
use RuntimeException;

class Field extends Base
{
    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'eid' => 'global',
    ];

    protected $config = null;

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->eid) {
                throw new RuntimeException('Not found entry id.');
            }
            $entryHelper = new EntryHelper($this->getBaseParams([]));
            if (!$entryHelper->canAccessEntry($this->eid)) {
                throw new RuntimeException('Permission denied.');
            }
            $entry = ACMS_RAM::entry($this->eid);
            $vars = [
                'eid' => (int) $entry['entry_id'],
                'code' => $entry['entry_code'],
                'title' => $entry['entry_title'],
                'link' => $entry['entry_link'],
                'indexing' => $entry['entry_indexing'],
                'datetime' => $entry['entry_datetime'],
                'createdAt' => $entry['entry_posted_datetime'],
                'updatedAt' => $entry['entry_updated_datetime'],
            ];
            $vars['fields'] = $this->buildFieldTrait(loadEntryField($this->eid));
            $vars['moduleFields'] = $this->buildModuleField();
            $vars['geo'] = null;
            if (config('geolocation_entry_function') === 'on') {
                $geo = loadGeometry('eid', $this->eid);
                $vars['geo'] = $this->buildFieldTrait($geo);
            } else {
                $vars['geo'] = null;
            }
            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }
}
