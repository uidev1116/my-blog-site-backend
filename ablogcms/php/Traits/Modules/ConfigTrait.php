<?php

namespace Acms\Traits\Modules;

trait ConfigTrait
{
    /**
     * @var array<string, mixed> $config
     */
    protected $config = [];

    /**
     * コンフィグの設定
     *
     * @return array<string, mixed>
     */
    abstract protected function initConfig(): array;

    /**
     * コンフィグのセット
     *
     * @return bool
     */
    protected function setConfigTrait(): bool
    {
        $this->config = $this->initConfig();
        if ($this->config === false) { // @phpstan-ignore-line
            return false;
        }
        return true;
    }
}
