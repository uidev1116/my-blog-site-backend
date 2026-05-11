<?php

use Acms\Services\Webhook\Validator as WebhookValidator;

class ACMS_POST_Webhook_Update extends ACMS_POST
{
    function post()
    {
        $input = $this->extract('webhook', new ACMS_Validator());
        $id = $this->Get->get('id', null);

        if ('global' !== $input->get('scope')) {
            $input->set('scope', 'local');
        }
        if ('on' !== $input->get('request-history')) {
            $input->set('request-history', 'off');
        }
        if ('custom' !== $input->get('payload')) {
            $input->set('payload', 'default');
        }

        $sql = SQL::newSelect('webhook');
        $sql->addSelect('webhook_blog_id');
        $sql->addWhereOpr('webhook_id', $id);
        $bid = DB::query($sql->get(dsn()), 'one');

        $input->setMethod('status', 'in', ['open', 'close']);
        $input->setMethod('name', 'required');
        $input->setMethod('name', 'maxlength', '255');
        $input->setMethod('type', 'required');
        $input->setMethod('type', 'in', ['entry', 'form', 'user']);
        $input->setMethod('events', 'required');
        $input->setMethod('events', 'maxlength', '128');
        $input->setMethod('url', 'required');
        $input->setMethod('url', 'webhookScheme');
        $input->setMethod('url', 'webhookWhitelist');
        $input->setMethod('webhook', 'operative', sessionWithAdministration($bid));
        $input->setMethod('secret', 'maxlength', '128');
        $input->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $sql = SQL::newUpdate('webhook');
            $sql->addUpdate('webhook_status', $input->get('status'));
            $sql->addUpdate('webhook_name', $input->get('name'));
            $sql->addUpdate('webhook_type', $input->get('type'));
            $sql->addUpdate('webhook_events', $input->get('events'));
            $sql->addUpdate('webhook_url', $input->get('url'));
            $sql->addUpdate('webhook_history', $input->get('history'));
            $sql->addUpdate('webhook_scope', $input->get('scope'));
            $sql->addUpdate('webhook_payload', $input->get('payload'));
            $sql->addUpdate('webhook_payload_tpl', $input->get('payload_tpl'));
            $sql->addUpdate('webhook_secret', $input->get('secret'));
            $sql->addUpdate('webhook_blog_id', BID);
            $sql->addWhereOpr('webhook_id', $id);
            DB::query($sql->get(dsn()), 'exec');

            $this->addMessage('Webhookを保存しました');

            AcmsLogger::info('Webhook「' . $input->get('name') . '」を保存しました', [
                'id' => $id,
                'data' => $input->_aryField,
            ]);
        } else {
            $this->addError('Webhookの保存に失敗しました');

            AcmsLogger::info('Webhookの保存に失敗しました', [
                'data' => $input,
            ]);
        }
        return $this->Post;
    }
}
