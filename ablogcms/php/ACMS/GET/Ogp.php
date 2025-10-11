<?php

use Acms\Modules\Get\Helpers\OgpHelper;

class ACMS_GET_Ogp extends ACMS_GET
{
    public $_scope = [
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'date' => 'global',
        'page' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get()
    {
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $config = Field::singleton('config');
        $ogpHelper = new OgpHelper(['config' => $config]);
        $ogpHelper->setGlue($config->get('ogp_title_delimiter', ' | '));
        $vars = [
            'title' => $ogpHelper->getTitle(),
            'description' => $ogpHelper->getDescription(),
            'keywords' => $ogpHelper->getKeywords(),
            'type' => $ogpHelper->getType(),
        ];

        $imageData = $ogpHelper->getImage();
        if ($imageData) {
            $vars = array_merge($vars, [
                'image' => $imageData['path'],
                'image@x' => $imageData['width'],
                'image@y' => $imageData['height'],
                'image@type' => $imageData['type'],
            ]);
        }

        return $Tpl->render($vars);
    }
}
