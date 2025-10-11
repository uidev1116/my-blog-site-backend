<?php

class ACMS_GET_Admin_Entry_BulkChange_Form extends ACMS_GET_Admin_Entry_BulkChange
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        try {
            $step = $this->Post->get('step');
            $error = $this->Post->get('error');
            $block = !(empty($step) or is_bool($step)) ? 'step#' . $step : 'step#1';
            $this->Post->delete('step');
            $this->Post->delete('error');
            if ($error) {
                $tpl->add('error:' . $error);
            }

            $entry = $this->Post->getChild('entry');

            // サブカテゴリーの選択肢を保持する
            /** @var int[] $subCategoryIds */
            $subCategoryIds = array_map('intval', array_map('trim', explode(',', $entry->get('entry_sub_category_id'))));
            if (count($subCategoryIds) > 0) {
                $subCategories = $this->findCategories($subCategoryIds);
                $entrySubCategoryIds = array_column($subCategories, 'id');
                $entrySubCategoryLabels = array_column($subCategories, 'label');
                $entry->setField('entry_sub_category_id', implode(',', $entrySubCategoryIds));
                $entry->setField('entry_sub_category_label', implode(',', $entrySubCategoryLabels));
            }
            $tpl->add($block, $this->buildField($this->Post, $tpl, $block, ''));
        } catch (\Exception $e) {
            AcmsLogger::debug($e->getMessage(), Common::exceptionArray($e));
        }
        return $tpl->get();
    }

    /**
     * @param int[] $categoryIds
     * @return array{
     *  id: int,
     *  label: string
     * }[]
     */
    protected function findCategories(array $categoryIds): array
    {
        $categories = [];
        $sql = SQL::newSelect('category');
        $sql->addWhereIn('category_id', $categoryIds);
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');
        if ($statement) {
            while ($row = DB::next($statement)) {
                $categories[] = [
                    'id' => (int)$row['category_id'],
                    'label' => (string)$row['category_name']
                ];
            }
        }
        return $categories;
    }
}
