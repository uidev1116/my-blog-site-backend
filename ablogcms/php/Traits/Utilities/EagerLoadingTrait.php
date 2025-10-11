<?php

namespace Acms\Traits\Utilities;

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Application;
use ACMS_Filter;
use Field;
use SQL;

trait EagerLoadingTrait
{
    /**
     * @param int[]|string[] $ids
     * @param 'eid'|'uid'|'bid'|'cid'|'mid'|'unit_id' $type
     * @param ?int $rvid
     * @return ($ids is int[] ? array<int, \Field> : array<string, \Field>)
     */
    protected function eagerLoadFieldTrait(array $ids, $type, ?int $rvid = null): array
    {
        $eagerLoadingData = [];
        $groupList = [];
        $mediaList = [];

        if (!$ids) {
            return $eagerLoadingData;
        }
        $db = DB::singleton(dsn());
        if ($type === 'eid' && ($rvid ?? false)) {
            $sql = SQL::newSelect('field_rev');
            $sql->addWhereOpr('field_rev_id', $rvid);
        } else {
            $sql = SQL::newSelect('field');
        }
        $sql->addSelect('field_key');
        $sql->addSelect('field_value');
        $sql->addSelect('field_type');
        $sql->addSelect('field_search');
        $sql->addSelect('field_' . $type, 'id');
        $sql->addWhereIn('field_' . $type, array_unique($ids));
        $sql->setOrder('field_sort');
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        while ($row = $db->next($statement)) {
            $id = intval($row['id']);
            if (!isset($groupList[$id])) {
                $groupList[$id] = [];
            }
            $groupList[$id][] = $row;
        }
        foreach ($groupList as $id => $fields) {
            foreach ($fields as $row) {
                $fd = $row['field_key'];
                if (strpos($fd, '@media') !== false) {
                    $mediaList[] = intval($row['field_value']);
                }
            }
        }
        if ($mediaList) {
            $sql = SQL::newSelect('media');
            $sql->addWhereIn('media_id', $mediaList);
            $q = $sql->get(dsn());
            $statement = $db->query($q, 'exec');
            $mediaList = [];
            while ($media = $db->next($statement)) {
                $mid = intval($media['media_id']);
                $mediaList[$mid] = $media;
            }
        }
        foreach ($groupList as $id => $fields) {
            $Field = new Field();
            $useMediaField = [];
            foreach ($fields as $row) {
                $fd = $row['field_key'];
                $Field->addField($fd, $row['field_value']);
                $Field->setMeta($fd, 'search', $row['field_search'] === 'on');
                $Field->setMeta($fd, 'type', $row['field_type']);
                if (strpos($fd, '@media') !== false) {
                    $useMediaField[] = substr($fd, 0, -6);
                }
            }
            Media::injectMediaField($Field, $mediaList, $useMediaField);
            $eagerLoadingData[$id] = $Field;
        }
        return $eagerLoadingData;
    }

    /**
     * 指定したエントリーカスタムフィールドのメインイメージをEagerLoadする
     *
     * @param array $entryIds
     * @param string $fieldName
     * @param integer|null $rvid
     * @return array<int, array>
     */
    protected function eagerLoadMainImageFieldTrait(array $entryIds, string $fieldName, ?int $rvid = null): array
    {
        if (!$entryIds) {
            return [];
        }
        $db = DB::singleton(dsn());
        if ($rvid ?? false) {
            $sql = SQL::newSelect('field_rev');
            $sql->addWhereOpr('field_rev_id', $rvid);
        } else {
            $sql = SQL::newSelect('field');
        }
        $sql->addSelect('field_value', 'midia_id');
        $sql->addSelect('field_eid', 'eid');
        $sql->addWhereOpr('field_key', "{$fieldName}@media");
        $sql->addWhereIn('field_eid', array_unique($entryIds));
        $sql->setOrder('field_sort');
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        $entryMediaList = [];
        $mediaList = [];
        while ($row = $db->next($statement)) {
            $entryMediaList[] = [
                'eid' => (int) $row['eid'],
                'mid' => (int) $row['midia_id'],
            ];
            $mediaList[] = $row['midia_id'];
        }
        $eagerLoadMediaList = Media::mediaEagerLoad($mediaList);

        return array_reduce($entryMediaList, function ($carry, $item) use ($eagerLoadMediaList) {
            $eid = $item['eid'];
            $mid = $item['mid'];
            if (isset($eagerLoadMediaList[$mid])) {
                $carry[$eid] = $eagerLoadMediaList[$mid];
            }
            return $carry;
        }, []);
    }

    /**
     * フルテキストのEagerLoading
     *
     * @param int[] $entryIds
     * @return array<int<1, max>, \Acms\Services\Unit\UnitCollection>
     */
    protected function eagerLoadFullTextTrait(array $entryIds): array
    {
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);

        return $unitRepository->eagerLoadUnits($entryIds);
    }

    /**
     * タグのEagerLoading
     *
     * @param int[] $eidArray
     * @param ?int $rvid
     * @return array
     */
    protected function eagerLoadTagTrait(array $eidArray, ?int $rvid = null): array
    {
        $eagerLoadingData = [];
        if (!$eidArray) {
            return $eagerLoadingData;
        }
        $table = 'tag';
        if ($rvid ?? false) {
            $table = 'tag_rev';
        }
        $db = DB::singleton(dsn());
        $sql = SQL::newSelect($table);
        $sql->addWhereIn('tag_entry_id', $eidArray);
        $sql->addOrder('tag_sort');
        if ($rvid ?? false) {
            $sql->addWhereOpr('tag_rev_id', $rvid);
        }
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        while ($tag = $db->next($statement)) {
            $eid = intval($tag['tag_entry_id']);
            if (!isset($eagerLoadingData[$eid])) {
                $eagerLoadingData[$eid] = [];
            }
            $eagerLoadingData[$eid][] = $tag;
        }
        return $eagerLoadingData;
    }

    /**
     * メインイメージのEagerLoading
     *
     * @param $entries
     * @param $target 'unit'|'field'
     * @param $fieldName ?string
     * @param ?int $rvid
     * @return ($target is 'field' ? array{
     *   unit: array<string, \Acms\Services\Unit\Contracts\Model>,
     *   media: array<int, array>,
     *   fieldMainImage: array<int, array>
     * } : array{
     *   unit: array<string, \Acms\Services\Unit\Contracts\Model>,
     *   media: array<int, array>,
     * })
     */
    protected function eagerLoadMainImageTrait(array $entries, $target = 'unit', $fieldName = '', ?int $rvid = null): array
    {
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $mainImageData = $unitRepository->eagerLoadPrimaryImageUnits($entries);
        $fieldName = $fieldName ? $fieldName : config('main_image_field_name', '');
        $entryIds = array_column($entries, 'entry_id');

        if ($target === 'field') {
            $mainImageData['fieldMainImage'] = $this->eagerLoadMainImageFieldTrait($entryIds, $fieldName, $rvid);
            $mainImageData['fieldMainImageKey'] = $fieldName;
        }
        return $mainImageData;
    }

    /**
     * 関連記事のEagerLoading
     *
     * @param int[] $eidArray
     * @return array<non-empty-array<non-empty-list<non-empty-array<string, mixed>>>>
     */
    protected function eagerLoadRelatedEntryTrait(array $eidArray): array
    {
        $eagerLoadingData = [];
        if (!$eidArray) {
            return $eagerLoadingData;
        }
        $db = DB::singleton(dsn());
        $sql = SQL::newSelect('relationship');
        $sql->addLeftJoin('entry', 'entry_id', 'relation_eid');
        ACMS_Filter::entrySession($sql);
        $sql->addWhereIn('relation_id', $eidArray);
        $sql->setOrder('relation_order', 'ASC');
        $q = $sql->get(dsn());
        $relations = $db->query($q, 'all');

        $entryIds = [];
        foreach ($relations as $relation) {
            $entryIds[] = $relation['relation_eid'];
        }
        $eagerLoadingEntry = $this->eagerLoadEntryTrait($entryIds);
        $eagerLoadingField = $this->eagerLoadFieldTrait($entryIds, 'eid');

        foreach ($relations as $relation) {
            $eid = $relation['relation_id'];
            $type = $relation['relation_type'];
            if (!isset($eagerLoadingData[$eid])) {
                $eagerLoadingData[$eid] = [];
            }
            if (!isset($eagerLoadingData[$eid][$type])) {
                $eagerLoadingData[$eid][$type] = [];
            }
            $targetEid = $relation['relation_eid'];
            $data = isset($eagerLoadingEntry[$targetEid]) ? $eagerLoadingEntry[$targetEid] : ['eid' => $targetEid];
            $data['field'] = isset($eagerLoadingField[$targetEid]) ? $eagerLoadingField[$targetEid] : null;
            $eagerLoadingData[$eid][$type][] = $data;
        }
        return $eagerLoadingData;
    }
    /**
     * エントリーのEagerLoading
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    protected function eagerLoadEntryTrait(array $ids): array
    {
        $eagerLoadingData = [];
        $db = DB::singleton(dsn());
        $sql = SQL::newSelect('entry');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addWhereIn('entry_id', $ids);
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        while ($entry = $db->next($statement)) {
            $eid = intval($entry['entry_id']);
            $eagerLoadingData[$eid] = [
                'eid' => $eid,
                'bid' => intval($entry['entry_blog_id']),
                'cid' => intval($entry['entry_category_id']),
                'uid' => intval($entry['entry_user_id']),
                'title' => addPrefixEntryTitle(
                    $entry['entry_title'],
                    $entry['entry_status'],
                    $entry['entry_start_datetime'],
                    $entry['entry_end_datetime'],
                    $entry['entry_approval']
                ),
                'url' => acmsLink([
                    'eid' => $eid,
                ]),
            ];
        }
        return $eagerLoadingData;
    }

    /**
     * サブカテゴリーのEagerLoading
     *
     * @param int[] $eidArray
     * @param null|int $rvid
     * @return array<array<int, array<string, mixed>>>
     */
    protected function eagerLoadSubCategoriesTrait(array $eidArray, ?int $rvid = null): array
    {
        $eagerLoadingData = [];
        if (!$eidArray) {
            return $eagerLoadingData;
        }
        $table = 'entry_sub_category';
        if ($rvid ?? false) {
            $table = 'entry_sub_category_rev';
        }
        $db = DB::singleton(dsn());
        $sql = SQL::newSelect($table);
        $sql->addLeftJoin('category', 'category_id', 'entry_sub_category_id');
        $sql->addWhereIn('entry_sub_category_eid', $eidArray);
        if ($rvid ?? false) {
            $sql->addWhereOpr('entry_sub_category_rev_id', $rvid);
        }
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        while ($item = $db->next($statement)) {
            $eid = intval($item['entry_sub_category_eid']);
            if (!isset($eagerLoadingData[$eid])) {
                $eagerLoadingData[$eid] = [];
            }
            $eagerLoadingData[$eid][] = $item;
        }
        return $eagerLoadingData;
    }
}
