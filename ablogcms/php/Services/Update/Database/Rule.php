<?php

namespace Acms\Services\Update\Database;

use Acms\Services\Facades\Database as DB;
use SQL;
use Field;

/**
 * このクラスは、バージョンアップ時に「特別な更新処理」を行うためのクラスです。
 *
 * 注意点：
 * - アップデート処理の実行時には、新しいバージョンで追加されたクラスやメソッドは、まだ読み込まれていないため利用できません。
 * - そのため、このクラスでは 「オンラインアップデート機能」が追加された v2.8.0 から利用できる機能でのみ実装してください。
 *
 * 例 DB::next() は利用できません。v3.2.0 で追加されたため。
 */
class Rule
{
    /**
     * @var string
     */
    protected $fromVersion;

    /**
     * @var string
     */
    protected $toVersion;

    /**
     * 例外的なアップデートを実行
     *
     * @param string $fromVersion
     * @param string $toVersion
     */
    public function update($fromVersion, $toVersion)
    {
        $this->fromVersion = $fromVersion;
        $this->toVersion = $toVersion;

        // v1.4.0以前
        if (version_compare($this->fromVersion, '1.4.0', '<')) {
            $this->update140();
        }
        // v1.4.2以前
        if (version_compare($this->fromVersion, '1.4.2', '<')) {
            $this->update142();
        }
        // v1.5.0以前
        if (version_compare($this->fromVersion, '1.5.0', '<')) {
            $this->update150();
        }
        // v2.10.0以前
        if (version_compare($this->fromVersion, '2.10.0', '<')) {
            $this->update2100();
        }

        // v3.1.49 以前 & v3.2.6 以前 のバージョンでsetupからのDBアップデートで以下の処理が実行されていないため、
        // バージョン比較をせずに実行するようにしています。
        // - update3150()
        // - update320()
        // - update321()

        // v3.1.50以前
        $this->update3150();

        // v3.2.0以前
        $this->update320();

        // v3.2.1以前
        $this->update321();
    }

    /**
     * フィールドグループのコンフィグを追加
     *
     * @param string $group
     * @param array $vals
     * @param null|int $bid
     * @param null|int $rid
     * @param null|int $mid
     */
    protected function addGroupConfig($group, $vals, $bid, $rid = null, $mid = null)
    {
        $DB = DB::singleton(dsn());
        foreach ($vals as $val) {
            $SQL = SQL::newInsert('config');
            $SQL->addInsert('config_key', $group);
            $SQL->addInsert('config_value', $val);
            $SQL->addInsert('config_sort', '0');
            if (!empty($mid)) {
                $SQL->addInsert('config_module_id', $mid);
            } elseif (!empty($rid)) {
                $SQL->addInsert('config_rule_id', $rid);
            }
            $SQL->addInsert('config_blog_id', $bid);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * navigation_publish = on を追加
     *
     * @param int $bid
     * @param null|int $rid
     * @param null|int $mid
     */
    protected function addNavigationPublish($bid, $rid = null, $mid = null)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('config');
        $SQL->addWhereOpr('config_key', 'navigation_target');
        $SQL->addWhereOpr('config_rule_id', $rid);
        $SQL->addWhereOpr('config_module_id', $mid);
        $SQL->addWhereOpr('config_blog_id', $bid);
        $SQL->addSelect('*', 'row_amount', null, 'COUNT');
        $SQL->addSelect('config_sort', 'max_sort', null, 'MAX');
        $res = $DB->query($SQL->get(dsn()), 'row');

        if (empty($res)) {
            return;
        }

        $range = range($res['max_sort'] + 1, $res['max_sort'] + $res['row_amount']);

        foreach ($range as $sort) {
            $SQL = SQL::newInsert('config');
            $SQL->addInsert('config_key', 'navigation_publish');
            $SQL->addInsert('config_value', 'on');
            $SQL->addInsert('config_sort', $sort);
            $SQL->addInsert('config_rule_id', $rid);
            $SQL->addInsert('config_module_id', $mid);
            $SQL->addInsert('config_blog_id', $bid);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * v1.4.0以前からのアップデート
     * コンフィグ画面用のconfigフィールドグループを追加
     */
    private function update140()
    {
        $DB = DB::singleton(dsn());

        // モジュールIDを探索
        $SQL = SQL::newSelect('module');
        $SQL->addWhereOpr('module_name', 'Links');
        $mods = $DB->query($SQL->get(dsn()), 'all');

        foreach ($mods as $mod) {
            $mid = $mod['module_id'];
            $bid = $mod['module_blog_id'];

            $this->addGroupConfig('@linkgroup', ['links_value', 'links_label'], $bid, null, $mid);
        }

        // ルールを探索
        $SQL = SQL::newSelect('rule');
        $rules = $DB->query($SQL->get(dsn()), 'all');

        foreach ($rules as $rule) {
            $rid = $rule['rule_id'];
            $bid = $rule['rule_blog_id'];

            $this->addGroupConfig(
                '@linkgroup',
                ['links_value', 'links_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@addtype_group',
                ['addtype_mimetype', 'addtype_extension'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_text_tag_group',
                ['column_text_tag', 'column_text_tag_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_image_size_group',
                ['column_image_size', 'column_image_size_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_map_size_group',
                ['column_map_size', 'column_map_size_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_youtube_size_group',
                ['column_youtube_size', 'column_youtube_size_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_eximage_size_group',
                ['column_eximage_size', 'column_eximage_size_label'],
                $bid,
                $rid,
                null
            );
            $this->addGroupConfig(
                '@column_add_type_group',
                ['column_add_type', 'column_add_type_label'],
                $bid,
                $rid,
                null
            );
        }
    }

    /**
     * v1.4.2以前からのアップデート
     * Api_Yahoo_* の名前変更対応
     */
    private function update142()
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newUpdate('module');
        $SQL->addUpdate('module_name', 'Api_Yahoo_WebSearch');
        $SQL->addWhereOpr('module_name', 'Api_YahooWebSearch');
        $DB->query($SQL->get(dsn()), 'exec');

        $SQL = SQL::newUpdate('module');
        $SQL->addUpdate('module_name', 'Api_Yahoo_ImageSearch');
        $SQL->addWhereOpr('module_name', 'Api_YahooImageSearch');
        $DB->query($SQL->get(dsn()), 'exec');

        // ユーザーfulltextを生成・追加
        $SQL = SQL::newSelect('user');
        $q = $SQL->get(dsn());
        $all = $DB->query($q, 'all');

        foreach ($all as $row) {
            // user
            $user = [
                $row['user_name'],
                $row['user_code'],
                $row['user_mail'],
                $row['user_mail_mobile'],
                $row['user_url']
            ];
            $uid = $row['user_id'];
            $bid = $row['user_blog_id'];

            // meta
            $meta = [];
            $SQL = SQL::newSelect('field');
            $SQL->addSelect('field_value');
            $SQL->addWhereOpr('field_search', 'on');
            $SQL->addWhereOpr('field_uid', $uid);
            $_q = $SQL->get(dsn());
            $all2 = $DB->query($_q, 'all');

            foreach ($all2 as $_row) {
                $meta[] = $_row['field_value'];
            }

            // merge
            $user = preg_replace('@\s+@', ' ', strip_tags(implode(' ', $user)));
            $meta = preg_replace('@\s+@', ' ', strip_tags(implode(' ', $meta)));
            $fulltext = $user . "\x0d\x0a\x0a\x0d" . $meta;

            // delete
            $SQL = SQL::newDelete('fulltext');
            $SQL->addWhereOpr('fulltext_uid', $uid);
            $DB->query($SQL->get(dsn()), 'exec');

            // save
            $SQL = SQL::newInsert('fulltext');
            $SQL->addInsert('fulltext_value', $fulltext);
            $SQL->addInsert('fulltext_uid', $uid);
            $SQL->addInsert('fulltext_blog_id', $bid);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * v1.5.0以前からのアップデート
     * navigation_publish = on を追加
     */
    private function update150()
    {
        $DB = DB::singleton(dsn());

        // モジュールIDを探索
        $SQL = SQL::newSelect('module');
        $SQL->addWhereOpr('module_name', 'Navigation');
        $mods = $DB->query($SQL->get(dsn()), 'all');

        if (!empty($mods)) {
            foreach ($mods as $mod) {
                $mid = $mod['module_id'];
                $bid = $mod['module_blog_id'];

                $this->addNavigationPublish($bid, null, $mid);
            }
        }

        // ルールを探索
        $SQL = SQL::newSelect('rule');
        $rules = $DB->query($SQL->get(dsn()), 'all');

        if (!empty($mods)) {
            foreach ($rules as $rule) {
                $rid = $rule['rule_id'];
                $bid = $rule['rule_blog_id'];

                $this->addNavigationPublish($bid, $rid, null);
            }
        }

        // デフォルトを探索
        $SQL = SQL::newSelect('blog');
        $blogs = $DB->query($SQL->get(dsn()), 'all');

        if (!empty($blogs)) {
            foreach ($blogs as $blog) {
                $bid = $blog['blog_id'];

                $this->addNavigationPublish($bid, null, null);
            }
        }
    }

    /**
     * v2.10.0以前からのアップデート
     * workflowを
     */
    private function update2100()
    {
        $DB = DB::singleton(dsn());

        $startList = [];
        $SQL = SQL::newSelect('config');
        $SQL->addWhereOpr('config_key', 'workflow_start_group');
        $q = $SQL->get(dsn());
        $all = $DB->query($q, 'all');
        foreach ($all as $row) {
            $startList[$row['config_blog_id']][] = $row['config_value'];
        }

        $lastList = [];
        $SQL = SQL::newSelect('config');
        $SQL->addWhereOpr('config_key', 'workflow_last_group');
        $q = $SQL->get(dsn());
        $all2 = $DB->query($q, 'all');
        foreach ($all2 as $row) {
            $lastList[$row['config_blog_id']][] = $row['config_value'];
        }

        foreach ($startList as $bid => $items) {
            $SQL = SQL::newUpdate('workflow');
            $SQL->addUpdate('workflow_start_group', implode(',', $items));
            $SQL->addWhereOpr('workflow_blog_id', $bid);
            $SQL->addWhereOpr('workflow_category_id', null);
            $DB->query($SQL->get(dsn()), 'exec');
        }

        foreach ($lastList as $bid => $items) {
            $SQL = SQL::newUpdate('workflow');
            $SQL->addUpdate('workflow_last_group', implode(',', $items));
            $SQL->addWhereOpr('workflow_blog_id', $bid);
            $SQL->addWhereOpr('workflow_category_id', null);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * v3.1.50以前からのアップデート
     * フィールドにタイプのカラムを追加
     * メディアフィールドの検索用インデックスのため
     *
     * @return void
     */
    private function update3150(): void
    {
        $sql = SQL::newUpdate('field');
        $sql->addUpdate('field_type', 'media');
        $sql->addWhereOpr('field_key', '%@media', 'LIKE');
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newUpdate('field');
        $sql->addUpdate('field_type', 'html');
        $sql->addWhereOpr('field_key', '%@html', 'LIKE');
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newUpdate('field');
        $sql->addUpdate('field_type', 'title');
        $sql->addWhereOpr('field_key', '%@title', 'LIKE');
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * v3.2.0以前からのアップデート
     * - カスタムユニットのフィールドデータをcolumnテーブルからフィールドテーブルに移動
     * - column_align カラムに存在する非表示を acms_column, acms_column_rev テーブルの column_status カラムのデータとして扱えるようにアップデート時にデータコンバートを行う
     *
     * @return void
     */
    private function update320(): void
    {
        $this->updateCustomUnitData();
        $this->updateUnitAlignToStatus();
    }

    /**
     * v3.2.0以前からのアップデート
     * カスタムユニットのフィールドデータをcolumnテーブルからフィールドテーブルに移動
     *
     * @return void
     */
    private function updateCustomUnitData(): void
    {
        // columnテーブルのcustomユニット情報をfieldテーブルに移動
        $sql = SQL::newSelect('column');
        $sql->addSelect('column_id');
        $sql->addSelect('column_field_6');
        $sql->addSelect('column_blog_id');
        $sql->addWhereOpr('column_type', 'custom_%', 'LIKE', 'OR');
        $sql->addWhereOpr('column_type', 'custom', '=', 'OR');
        $q = $sql->get(dsn());
        $all = DB::query($q, 'all');

        $unitIds = array_column($all, 'column_id');
        if (count($unitIds) > 0) {
            $selectFieldSql = SQL::newSelect('field');
            $selectFieldSql->addSelect('field_unit_id');
            $selectFieldSql->addWhereOpr('field_unit_id', $unitIds[0]);
            $q = $selectFieldSql->get(dsn());
            $isConverted = !!DB::query($q, 'one');
            // すでに移行済みのデータが存在する場合はスキップ
            // すべてのユニットデータが field テーブルに移行済みかどうかを確認するのは、パフォーマンス的に心配なので
            // 最初のユニットデータが field テーブルに移行済みかどうかを確認するようにしています。
            if (!$isConverted) {
                foreach ($all as $item) {
                    $id = (string) $item['column_id'];
                    $bid = (int) $item['column_blog_id'];
                    $field = function_exists('acmsDangerUnserialize') ? acmsDangerUnserialize($item['column_field_6']) : acmsUnserialize($item['column_field_6']);
                    if ($id !== '' && ($field instanceof Field)) {
                        foreach ($field->listFields() as $fd) {
                            foreach ($field->getArray($fd, true) as $i => $val) {
                                $fieldTypeValue = null;
                                if (preg_match('/@(html|media|title)$/', $fd, $match)) {
                                    $fieldTypeValue = $match[1];
                                }
                                if ($fieldType = $field->getMeta($fd, 'type')) {
                                    $fieldTypeValue = $fieldType;
                                }
                                $data = [
                                    'field_key' => $fd,
                                    'field_value' => $val,
                                    'field_type' => $fieldTypeValue,
                                    'field_sort' => $i + 1,
                                    'field_search' => $field->getMeta($fd, 'search') ? 'on' : 'off',
                                    'field_unit_id' => $id,
                                    'field_blog_id' => $bid,
                                ];
                                $insertFieldSql = SQL::newInsert('field');
                                foreach ($data as $key => $value) {
                                    $insertFieldSql->addInsert($key, $value);
                                }
                                DB::query($insertFieldSql->get(dsn()), 'exec');
                            }
                        }
                    }
                }
            }
        }
        // column_revテーブルのcustomユニット情報をfield_revテーブルに移動
        $sql = SQL::newSelect('column_rev');
        $sql->addSelect('column_id');
        $sql->addSelect('column_rev_id');
        $sql->addSelect('column_field_6');
        $sql->addSelect('column_blog_id');
        $sql->addWhereOpr('column_type', 'custom_%', 'LIKE', 'OR');
        $sql->addWhereOpr('column_type', 'custom', '=', 'OR');
        $q = $sql->get(dsn());
        $all = DB::query($q, 'all');
        $unitIds = array_column($all, 'column_id');
        if (count($unitIds) > 0) {
            $selectFieldRevSql = SQL::newSelect('field_rev');
            $selectFieldRevSql->addSelect('field_unit_id');
            $selectFieldRevSql->addWhereOpr('field_unit_id', $unitIds[0]);
            $q = $selectFieldRevSql->get(dsn());
            $isConverted = !!DB::query($q, 'one');
            // すでに移行済みのデータが存在する場合はスキップ
            // すべてのユニットデータが field_rev テーブルに移行済みかどうかを確認するのは、パフォーマンス的に心配なので
            // 最初のユニットデータが field_rev テーブルに移行済みかどうかを確認するようにしています。
            if (!$isConverted) {
                foreach ($all as $item) {
                    $id = (string) $item['column_id'];
                    $bid = (int) $item['column_blog_id'];
                    $rvid = (int) $item['column_rev_id'];
                    $field = function_exists('acmsDangerUnserialize') ? acmsDangerUnserialize($item['column_field_6']) : acmsUnserialize($item['column_field_6']);
                    if ($id !== '' && ($field instanceof Field)) {
                        foreach ($field->listFields() as $fd) {
                            foreach ($field->getArray($fd, true) as $i => $val) {
                                $fieldTypeValue = null;
                                if (preg_match('/@(html|media|title)$/', $fd, $match)) {
                                    $fieldTypeValue = $match[1];
                                }
                                if ($fieldType = $field->getMeta($fd, 'type')) {
                                    $fieldTypeValue = $fieldType;
                                }
                                $data = [
                                    'field_key' => $fd,
                                    'field_value' => $val,
                                    'field_type' => $fieldTypeValue,
                                    'field_sort' => $i + 1,
                                    'field_search' => $field->getMeta($fd, 'search') ? 'on' : 'off',
                                    'field_unit_id' => $id,
                                    'field_rev_id' => $rvid,
                                    'field_blog_id' => $bid,
                                ];
                                $insertFieldRevSql = SQL::newInsert('field_rev');
                                foreach ($data as $key => $value) {
                                    $insertFieldRevSql->addInsert($key, $value);
                                }
                                DB::query($insertFieldRevSql->get(dsn()), 'exec');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * v3.2.0以前からのアップデート
     * column_align カラムに存在する非表示を acms_column, acms_column_rev テーブルの column_status カラムのデータとして扱えるようにアップデート時にデータコンバートを行う
     * 配置は center に変更する
     *
     * @return void
     */
    private function updateUnitAlignToStatus(): void
    {
        $sql = SQL::newUpdate('column');
        $sql->addUpdate('column_status', 'close');
        $sql->addUpdate('column_align', 'center');
        $sql->addWhereOpr('column_align', 'hidden');
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newUpdate('column_rev');
        $sql->addUpdate('column_status', 'close');
        $sql->addUpdate('column_align', 'center');
        $sql->addWhereOpr('column_align', 'hidden');
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * v3.1.55以前からのアップデート
     *
     * 監査ログの秘匿化項目をマスク化
     *
     * @return void
     */
    private function update321(): void
    {
        $sql = SQL::newSelect('audit_log');
        $sql->addWhereOpr('audit_log_acms_post', 'Member\_%', 'LIKE');
        $sql->addWhereOpr('audit_log_req_body', null, '<>');
        $sql->addWhereOpr('audit_log_req_body', '[]', '<>');
        $sql->addWhereOpr('audit_log_req_body', '', '<>');
        $all = DB::query($sql->get(dsn()), 'all');
        if (!$all) {
            return;
        }
        foreach ($all as $row) {
            $body = json_decode($row['audit_log_req_body'], true);
            if (!$body || !is_array($body)) {
                continue;
            }
            $data = $this->filterArray($body);
            $data = json_encode($data);

            $update = SQL::newUpdate('audit_log');
            $update->addUpdate('audit_log_req_body', $data ? $data : null);
            $update->addWhereOpr('audit_log_id', $row['audit_log_id']);
            DB::query($update->get(dsn()), 'exec');
        }
    }

    /**
     * 配列を再帰的にフィルタリング
     *
     * @param array $data
     * @return array
     */
    private function filterArray(array $data): array
    {
        $filtered = [];

        // 機密フィールドのリスト（部分一致用）
        $sensitiveFields = [
            'password',
            'passwd',
            'pwd',
            'pass',
            'retype_pass',
            'code',
            'recovery',
            'takeover',
            'token',
            'api_key',
            'secret',
            'formUniqueToken',
            'formToken',
        ];

        foreach ($data as $key => $value) {
            $filtered[$key] = match (true) {
                is_array($value) => $this->filterArray($value),
                is_string($key) && array_any(
                    $sensitiveFields,
                    fn(string $sensitiveField): bool => str_contains(strtolower($key), strtolower($sensitiveField))
                ) => '***MASKED***',
                default => $value
            };
        }
        return $filtered;
    }
}
