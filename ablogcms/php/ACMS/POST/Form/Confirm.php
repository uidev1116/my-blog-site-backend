<?php

use Acms\Services\Facades\PublicStorage;

class ACMS_POST_Form_Confirm extends ACMS_POST_Form
{
    function post()
    {
        $id = $this->Post->get('id');

        // フォーム情報のロード
        $info = $this->loadForm($id);
        if (empty($info)) {
            AcmsLogger::critical('フォームID「' . $id . '」が存在しないため、フォームの処理を中断しました');
            $this->Post->set('step', 'forbidden');
            return $this->Post;
        }
        $Form = $info['data'];

        // サーバサイドのバリデーションを実装
        $Option = new Field();
        $Option->overload($Form->getChild('option'));
        $dup = $this->buildOptions($Option);

        // POSTの整形
        $Field = $this->extract('field');
        if (!empty($dup)) {
            list($fd, $mail) = $dup;
            $Field->setMethod($fd, 'duplication', $this->mailToDouble($info['id'], $mail));
        }

        // 添付ファイルの移動
        if ($Form->getChild('mail')->get('AdminAttachment') === 'on') {
            $this->modifyAttachedFile($Field);
        }
        $Field->validate(new ACMS_Validator());

        return $this->Post;
    }

    /**
     * 添付ファイルの移動
     * 添付ファイルを後で削除する為、一時ディレクトリに移動し、Fieldの書き換えを行う
     *
     * @param Field $Field
     */
    function modifyAttachedFile(&$Field)
    {
        PublicStorage::makeDirectory(ARCHIVES_DIR . config('mail_attachment_temp_dir'));

        foreach ($Field->listFields() as $fd) {
            $pathInfo = $this->getAttachedFilePath($Field, $fd);

            if ($pathInfo === false) {
                continue;
            }

            $realpath   = $pathInfo['realpath'];
            $temppath   = $pathInfo['temppath'];
            $fieldpath  = $pathInfo['fieldpath'];
            $fdname     = $pathInfo['fdname'];

            if (PublicStorage::copy($realpath, $temppath)) {
                PublicStorage::remove($realpath);

                $Field->set($fd, $fieldpath);
                $Field->set($fdname . '@secret', md5($fdname . '@' . $fieldpath));
            }
        }
    }
}
