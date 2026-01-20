<?php

class ACMS_POST_Form_Duplicate extends ACMS_POST_Form
{
    public function post()
    {
        $fmid = intval($this->Get->get('fmid')) ?: null;

        if (roleAvailableUser()) {
            $this->Post->setMethod('form', 'operative', roleAuthorization('form_edit', BID));
        } else {
            $this->Post->setMethod('form', 'operative', sessionWithFormAdministration());
        }
        $this->Post->setMethod('form', 'fmidIsNull', $fmid);
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $form = $this->findFormById($fmid);
            $newId = $this->duplicate($fmid);

            AcmsLogger::info('「' . $form['name'] . '（' . $form['code'] . '）」フォームを複製しました', [
                'sourceFormId' => $fmid,
                'createdFormId' => $newId,
            ]);

            $url = acmsLink([
                'bid' => BID,
                'admin' => 'form_edit',
                'query' => [
                    'fmid' => $newId,
                    'edit' => 'update',
                ],
            ]);
            $this->redirect($url);
        } else {
            AcmsLogger::info('フォームの複製に失敗しました', [
                'fmid' => $fmid,
            ]);
        }
        return $this->Post;
    }


    /**
     * フォーム設定を複製
     *
     * @param int $fmid
     * @return int
     */
    protected function duplicate(int $fmid): int
    {
        $newId = intval(DB::query(SQL::nextval('form_id', dsn()), 'seq'));

        $sql = SQL::newSelect('form');
        $sql->addWhereOpr('form_id', $fmid);
        $sql->addWhereOpr('form_blog_id', BID);
        /** @var array{ form_code: string, form_name: string } $base */
        $base = DB::query($sql->get(dsn()), 'row');

        $base['form_id'] = $newId;
        $base['form_code'] .= config('form_code_duplicate_suffix') . $newId;
        $base['form_name'] .= config('form_name_duplicate_suffix');
        $base['form_current_serial'] = 0;

        $sql = SQL::newInsert('form');
        foreach ($base as $key => $val) {
            $sql->addInsert($key, $val);
        }
        DB::query($sql->get(dsn()), 'exec');

        return $newId;
    }
}
