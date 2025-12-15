<?php

namespace Acms\Services\JQuery;

class Helper
{
    /**
     * @var array{version: string, migrate: string}
     */
    protected $config;

    /**
     * Helper constructor.
     */
    public function __construct()
    {
        $this->config = include(PHP_DIR . 'config/jquery.php');
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->config['version'];
    }

    /**
     * @return string
     */
    public function getMigrate()
    {
        return $this->config['migrate'];
    }
}
