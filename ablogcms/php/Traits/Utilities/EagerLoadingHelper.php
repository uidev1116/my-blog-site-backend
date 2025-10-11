<?php

namespace Acms\Traits\Utilities;

class EagerLoadingHelper
{
    use EagerLoadingTrait;

    /**
     * @param int[]|string[] $ids
     * @param 'eid'|'uid'|'bid'|'cid'|'mid'|'unit_id' $type
     * @return ($ids is int[] ? array<int, \Field> : array<string, \Field>)
     */
    public function eagerLoadFieldPublic(array $ids, $type): array
    {
        return $this->eagerLoadFieldTrait($ids, $type);
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function eagerLoadEntryPublic(array $ids): array
    {
        return $this->eagerLoadEntryTrait($ids);
    }

    /**
     * @param int[] $eidArray
     * @return array<array<int, array<string, mixed>>>
     */
    public function eagerLoadSubCategoriesPublic(array $eidArray): array
    {
        return $this->eagerLoadSubCategoriesTrait($eidArray);
    }

    public function eagerLoadMainImagePublic(array $entries, string $target = 'unit', string $fieldName = '', ?int $rvid = null): array
    {
        return $this->eagerLoadMainImageTrait($entries, $target, $fieldName, $rvid);
    }
}
