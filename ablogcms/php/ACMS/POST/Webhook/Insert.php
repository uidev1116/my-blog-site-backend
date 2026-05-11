<?php

class ACMS_POST_Webhook_Insert extends ACMS_POST_Module
{
    function post()
    {
        $input = $this->extract('webhook', new ACMS_Validator());
        if ('global' !== $input->get('scope')) {
            $input->set('scope', 'local');
        }
        if ('on' !== $input->get('request-history')) {
            $input->set('request-history', 'off');
        }
        if ('custom' !== $input->get('payload')) {
            $input->set('payload', 'default');
        }

        $input->setMethod('status', 'in', ['open', 'close']);
        $input->setMethod('name', 'required');
        $input->setMethod('name', 'maxlength', '255');
        $input->setMethod('type', 'required');
        $input->setMethod('type', 'maxlength', '32');
        $input->setMethod('events', 'required');
        $input->setMethod('events', 'maxlength', '128');
        $input->setMethod('url', 'required');
        $input->setMethod('url', 'webhookScheme');
        $input->setMethod('url', 'webhookWhitelist');
        $input->setMethod('webhook', 'operative', sessionWithAdministration());
        $input->setMethod('secret', 'maxlength', '128');
        $input->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $id = DB::query(SQL::nextval('webhook_id', dsn()), 'seq');

            $sql = SQL::newInsert('webhook');
            $sql->addInsert('webhook_id', $id);
            $sql->addInsert('webhook_status', $input->get('status'));
            $sql->addInsert('webhook_name', $input->get('name'));
            $sql->addInsert('webhook_type', $input->get('type'));
            $sql->addInsert('webhook_events', $input->get('events'));
            $sql->addInsert('webhook_url', $input->get('url'));
            $sql->addInsert('webhook_history', $input->get('history'));
            $sql->addInsert('webhook_scope', $input->get('scope'));
            $sql->addInsert('webhook_payload', $input->get('payload'));
            $sql->addInsert('webhook_payload_tpl', $input->get('payload_tpl'));
            $sql->addInsert('webhook_secret', $input->get('secret'));
            $sql->addInsert('webhook_blog_id', BID);
            DB::query($sql->get(dsn()), 'exec');

            $this->addMessage('Webhookを作成しました');

            AcmsLogger::info('Webhook「' . $input->get('name') . '」を作成しました', [
                'id' => $id,
                'data' => $input->_aryField,
            ]);
        } else {
            $this->addError('Webhookの作成に失敗しました');

            AcmsLogger::info('Webhookの作成に失敗しました', [
                'data' => $input,
            ]);
        }
        return $this->Post;
    }
}
