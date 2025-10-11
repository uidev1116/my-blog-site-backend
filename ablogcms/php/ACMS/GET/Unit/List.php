<?php

use Acms\Modules\Get\Helpers\Entry\UnitListHelper;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Application;

class ACMS_GET_Unit_List extends ACMS_GET_Entry_Summary
{
    public $_axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    public $_scope = [
        'cid' => 'global',
        'eid' => 'global',
        'start' => 'global',
        'end' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\UnitListHelper
     */
    protected $unitListHelper;

    /**
     * コンフィグの取得
     *
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'order' => [config('column_list_order')],
            'orderFieldName' => '',
            'noNarrowDownSort' => false,
            'limit' => (int) config('column_list_limit'),
            'offset' => 0,
            'unit' => 1,
            'parentLoopClass' => config('column_list_parent_loop_class'),
            'loopClass' => config('column_list_loop_class'),
            'newItemPeriod' => 0,
            'displayIndexingOnly' => true,
            'displayMembersOnly' => false,
            'displaySubcategoryEntries' => false,
            'displaySecretEntry' => false,
            'dateOn' => true,
            'detailDateOn' => false,
            'notfoundBlock' => false,
            'notfoundStatus404' => false,
            'fulltextEnabled' => false,
            'fulltextWidth' => 0,
            'fulltextMarker' => '',
            'includeTags' => false,
            'hiddenCurrentEntry' => false,
            'hiddenPrivateEntry' => false,
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => false,
            'mainImageTarget' => 'unit',
            'mainImageFieldName' => '',
            'displayNoImageEntry' => false,
            'imageX' => 200,
            'imageY' => 200,
            'imageTrim' => false,
            'imageZoom' => false,
            'imageCenter' => false,
            // ページネーション
            'simplePagerEnabled' => false,
            'paginationEnabled' => true,
            'paginationDelta' => (int) config('column_list_pager_delta', 3),
            'paginationCurrentAttr' => config('column_list_pager_cur_attr'),
            // フィールド・情報
            'includeEntry' => config('column_list_entry_on') === 'on',
            'includeEntryFields' => config('column_list_entry_field') === 'on',
            'includeCategory' => config('column_list_category_on') === 'on',
            'includeCategoryFields' => config('column_list_category_field_on') === 'on',
            'includeUser' => config('column_list_user_on') === 'on',
            'includeUserFields' => config('column_list_user_field_on') === 'on',
            'includeBlog' => config('column_list_blog_on') === 'on',
            'includeBlogFields' => config('column_list_blog_field_on') === 'on',
            // 表示モード
            'relatedEntryMode' => false,
            'relatedEntryType' => '',
        ];
    }


    function get()
    {
        if (!$this->setConfigTrait()) {
            return '';
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper::buildModuleField($tpl);
        $this->boot();
        $vars = [];
        $sql = $this->unitListHelper->buildUnitListQuery();
        $this->countSql = $this->buildCountQuery();
        $unitData = Database::query($sql->get(dsn()), 'all');
        if (count($unitData) > $this->config['limit']) {
            array_pop($unitData);
        }
        if (count($unitData) > 0) {
            $vars += $this->buildUnitTemplate($tpl, $unitData);
            $vars += $this->buildFullspecPager($tpl);
        }
        $vars = array_merge($vars, $this->getRootVars());
        $tpl->add(null, $vars);

        return $tpl->get();
    }

    /**
     * 起動処理
     *
     * @return void
     */
    protected function boot(): void
    {
        parent::boot();
        $this->unitListHelper = new UnitListHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * 件数取得用のクエリを組み立て
     *
     * @return SQL_Select
     */
    protected function buildCountQuery(): SQL_Select
    {
        return $this->unitListHelper->getCountQuery();
    }

    protected function buildUnitTemplate(Template $tpl, array $unitData): array
    {
        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');
        $collection = $unitRepository->loadModels($unitData);
        $unitRepository->eagerLoadCustomUnitFields($collection);
        $mediaEagerLoading = Media::mediaEagerLoadFromUnit($collection);
        $vars = [];

        foreach ($unitData as $row) {
            $model = $unitRepository->loadModel($row);
            if (empty($model)) {
                continue;
            }
            if ($model instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
                $model->setEagerLoadedMedia($mediaEagerLoading);
            }
            $eid = (int) $row['entry_id'];
            $cid = (int) $row['category_id'];
            $bid = (int) $row['blog_id'];
            $uid = (int) $row['entry_user_id'];

            if ($model instanceof \Acms\Services\Unit\Contracts\UnitListModule) {
                $row += $model->renderUnitListModule($tpl);
            }
            $row['entry_url'] = acmsLink([
                'bid' => $bid,
                'eid' => $eid,
            ]);
            if (!empty($cid)) {
                $row['category_url'] = acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ]);
            } else {
                unset($row['category_name']);
            }
            $row['blog_url'] = acmsLink([
                'bid' => $bid,
            ]);

            $tmp = [];
            foreach ($row as $key => $val) {
                if (empty($val)) {
                    unset($row[$key]);
                }
                $tmp[preg_replace('/column/', 'unit', $key)] = $val;
            }
            $row = $tmp;

            $row['unit:loop.class'] = $this->config['loopClass'] ?? '';

            //-------------
            // entry field
            if ($this->config['includeEntry']) {
                if ($this->config['includeEntryFields']) {
                    $Field = loadEntryField($eid);
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldEntryTitle', ACMS_RAM::entryTitle($eid));
                $Field->setField('fieldEntryCode', ACMS_RAM::entryCode($eid));
                $Field->setField('fieldEntryDatetime', ACMS_RAM::entryDatetime($eid));

                $tpl->add(['entryField', 'unit:loop'], TemplateHelper::buildField($Field, $tpl, 'unit:loop'));
            }

            //-------------
            // user field
            if ($this->config['includeUser']) {
                if ($this->config['includeUserFields']) {
                    $Field = loadUserField($uid);
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldUserName', ACMS_RAM::userName($uid));
                $Field->setField('fieldUserCode', ACMS_RAM::userCode($uid));
                $Field->setField('fieldUserStatus', ACMS_RAM::userStatus($uid));
                $Field->setField('fieldUserMail', ACMS_RAM::userMail($uid));
                $Field->setField('fieldUserMailMobile', ACMS_RAM::userMailMobile($uid));
                $Field->setField('fieldUserUrl', ACMS_RAM::userUrl($uid));
                $Field->setField('fieldUserIcon', loadUserIcon($uid));
                if ($large = loadUserLargeIcon($uid)) {
                    $Field->setField('fieldUserLargeIcon', $large);
                }
                if ($orig = loadUserOriginalIcon($uid)) {
                    $Field->setField('fieldUserOrigIcon', $orig);
                }
                $tpl->add(['userField', 'unit:loop'], TemplateHelper::buildField($Field, $tpl, 'unit:loop'));
            }

            //------------
            // blog field
            if ($this->config['includeBlog']) {
                if ($this->config['includeBlogFields']) {
                    $Field = loadBlogField($bid);
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldBlogName', ACMS_RAM::blogName($bid));
                $Field->setField('fieldBlogCode', ACMS_RAM::blogCode($bid));
                $Field->setField('fieldBlogUrl', acmsLink(['bid' => $bid, '_protocol' => 'http'], false));

                $tpl->add(['blogField', 'unit:loop'], TemplateHelper::buildField($Field, $tpl, 'unit:loop'));
            }

            //----------------
            // category field
            if (!empty($cid) && $this->config['includeCategory']) {
                if ($this->config['includeCategoryFields']) {
                    $Field = loadCategoryField($cid);
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldCategoryName', ACMS_RAM::categoryName($cid));
                $Field->setField('fieldCategoryCode', ACMS_RAM::categoryCode($cid));
                $Field->setField('fieldCategoryUrl', acmsLink(['cid' => $cid, '_protocol' => 'http'], false));
                $Field->setField('fieldCategoryId', $cid);

                $tpl->add(['categoryField', 'unit:loop'], TemplateHelper::buildField($Field, $tpl, 'unit:loop'));
            }

            $tpl->add('column:loop', $row);
            $tpl->add('unit:loop', $row);
        }
        return $vars;
    }


    /**
     * ルート変数を取得
     *
     * @return array<string, mixed>
     */
    public function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->config['parentLoopClass'] ?? '',
        ];
    }
}
