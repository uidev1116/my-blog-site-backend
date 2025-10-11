<?php

use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Logger as AcmsLogger;

class ACMS_POST_Fix extends ACMS_POST
{
    /**
     * バリデート
     *
     * @param Field_Validation $field
     * @return bool
     */
    protected function validate(Field_Validation $field): bool
    {
        if (!sessionWithAdministration()) {
            return false;
        }
        $field->setMethod('fix_replacement_target', 'required');
        $field->setMethod('fix_replacement_pattern', 'required');
        $field->setMethod('fix_replacement_replacement', 'required');

        $field->validate(new ACMS_Validator());

        return $field->isValidAll();
    }

    /**
     * 置換設定を取得
     *
     * @param Field $field
     * @return array{string, string, string, string}
     */
    protected function getReplaceSetting(Field $field): array
    {
        return [
            $field->get('fix_replacement_target'),
            preg_quote($field->get('fix_replacement_pattern'), '@'),
            $field->get('fix_replacement_replacement'),
            $field->get('fix_replacement_target_cf_filter'),
        ];
    }

    /**
     * ログの残す
     *
     * @param string $target
     * @param int $updated
     * @param string $pattern
     * @param string $replacement
     * @return void
     */
    protected function saveLog(string $target, int $updated, string $pattern, string $replacement)
    {
        if ($updated > 0) {
            $nameMap = [
                'title' => 'タイトル',
                'unit' => 'テキストユニット',
                'custom_unit' => 'カスタムユニット',
                'field' => 'カスタムフィールド',
            ];
            $targetName = $nameMap[$target] ?? '';
            AcmsLogger::info("{$updated}件、エントリーの「{$targetName}」のテキスト置換を実行しました「{$pattern}」->「{$replacement}」");
        }
    }

    /**
     * 置換対象のブログIDを取得
     *
     * @param bool $includeChildBlogs
     * @return int[]
     */
    protected function targetBlog(bool $includeChildBlogs = false): array
    {
        if ($includeChildBlogs) {
            $blog = SQL::newSelect('blog');
            $blog->setSelect('blog_id');
            ACMS_Filter::blogTree($blog, BID, 'descendant-or-self');
            return DB::query($blog->get(dsn()), 'list');
        }
        return [BID];
    }

    /**
     * 完了後の処理
     *
     * @param int $updated
     * @return void
     */
    protected function completeProcess(int $updated): void
    {
        Cache::flush('temp');
        $this->Post->set('updated', $updated);
        $this->Post->set('message', 'success');
    }
}
