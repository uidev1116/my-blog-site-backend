<?php

class ACMS_POST_Category_Index_Parent extends ACMS_POST_Category
{
    function post()
    {
        if (!sessionWithCompilation()) {
            AcmsLogger::warning('権限がないため、指定されたカテゴリーの親カテゴリー変更に失敗しました');
            die403();
        }
        $toPid = idval($this->Post->get('parent'));
        if (!empty($_POST['checks']) and is_array($_POST['checks'])) {
            $aryCid = [];
            foreach ($_POST['checks'] as $cid) {
                if (
                    1
                    and !empty($toPid)
                    and ACMS_RAM::categoryScope($toPid) <> ACMS_RAM::categoryScope($cid)
                ) {
                    continue;
                }

                // implment ACMS_POST_Category
                $this->changeParentCategory($cid, $toPid);

                $aryCid[] = $cid;
            }
            $name = empty($toPid) ? 'なし' : ACMS_RAM::categoryName($toPid);
            AcmsLogger::info('指定されたカテゴリーの親カテゴリーを「' . $name . '」に変更', [
                'targetCIDs' => implode(',', $aryCid),
            ]);
        }

        return $this->Post;
    }
}
