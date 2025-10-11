<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;

class Links extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $vars = [
            'items' => [],
            'moduleFields' => $this->buildModuleField(),
        ];

        if (!$labels = $config->getArray('links_label')) {
            return $vars;
        }

        $urls = $config->getArray('links_value');
        $links = [];
        foreach ($labels as $i => $label) {
            $url = isset($urls[$i]) ? $urls[$i] : '';
            $links[] = [
                'url' => setGlobalVars($url),
                'name' => $label,
            ];
        }
        $vars['items'] = $links;

        return $vars;
    }
}
