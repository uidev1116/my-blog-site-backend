<?php

namespace Acms\Services\Unit;

use Acms\Services\Container as ServiceContainer;

class Registry extends ServiceContainer
{
    /**
     * ユニット設定用のオプションを取得する
     * @return array{
     *   value: string,
     *   label: string
     * }[]
     */
    public function getOptions(): array
    {
        $options = [];
        /** @var array<string, \stdClass> $aliasList */
        $aliasList = $this->aliasList();
        foreach ($aliasList as $info) {
            if (
                class_exists($info->class) &&
                is_subclass_of($info->class, \Acms\Services\Unit\Contracts\Model::class)
            ) {
                $options[] = [
                    'value' => $info->class::getUnitType(),
                    'label' => $info->class::getUnitLabel(),
                ];
            }
        }
        return $options;
    }

    /**
     * ユニットがinplace編集（ダイレクト編集）でサポートされているか
     * @param string $alias
     * @return bool
     */
    public function isInplaceSupported(string $alias): bool
    {
        $class = $this->findClassByAlias($alias);
        if ($class === null) {
            return false;
        }
        if (!class_exists($class)) {
            return false;
        }

        if (is_subclass_of($class, \Acms\Services\Unit\Contracts\ParentUnit::class)) {
            // 親ユニットになれる場合はinplace編集できない
            return false;
        }

        return true;
    }

    /**
     * ユニットが親ユニットになれるか
     * @param string $alias
     * @return bool
     */
    public function isParentUnit(string $alias): bool
    {
        $class = $this->findClassByAlias($alias);
        if ($class === null) {
            return false;
        }
        if (!class_exists($class)) {
            return false;
        }
        if (!is_subclass_of($class, \Acms\Services\Unit\Contracts\ParentUnit::class)) {
            return false;
        }
        return true;
    }

    /**
     * ユニットがサイズオプションを持つか
     * @return string[]
     */
    public function getSizableTypes(): array
    {
        $types = [];
        /** @var array<string, \stdClass> $aliasList */
        $aliasList = $this->aliasList();
        foreach ($aliasList as $info) {
            if (!class_exists($info->class)) {
                continue;
            }
            if (!is_subclass_of($info->class, \Acms\Services\Unit\Contracts\Model::class)) {
                continue;
            }
            if (!is_subclass_of($info->class, \Acms\Services\Unit\Contracts\SizeableUnitInterface::class)) {
                continue;
            }
            $types[] = $info->class::getUnitType();
        }
        return $types;
    }

    /**
     * エイリアスからクラスを取得
     * @param string $alias
     * @return class-string|null
     */
    public function findClassByAlias(string $alias): ?string
    {
        return $this->aliasList()[$alias]->class ?? null;
    }
}
