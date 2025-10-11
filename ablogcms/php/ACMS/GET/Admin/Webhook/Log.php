<?php

class ACMS_GET_Admin_Webhook_Log extends ACMS_GET_Admin
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $id = $this->Get->get('id');
        $sql = SQL::newSelect('webhook');
        $sql->addSelect('webhook_blog_id');
        $sql->addWhereOpr('webhook_id', $id);
        $bid = DB::query($sql->get(dsn()), 'one');

        if (!sessionWithAdministration($bid)) {
            die403();
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $webhook = loadWebhook($id);
        $vars = $this->buildField($webhook, $Tpl, 'webhook');
        $showResponseView = env('WEBHOOK_RESPONSE_VIEW', 'disable') === 'enable';

        $sql = SQL::newSelect('log_webhook');
        $sql->addWhereOpr('log_webhook_id', $id);
        $sql->addOrder('log_webhook_datetime', 'DESC');
        $sql->setLimit(100, 0);
        $q = $sql->get(dsn());
        $histories = DB::query($q, 'all');

        if (empty($histories)) {
            $Tpl->add('notFound');
        } else {
            foreach ($histories as $i => $history) {
                $data = [
                    'key' => md5($history['log_webhook_datetime'] . $i),
                    'datetime' => $history['log_webhook_datetime'],
                    'status' => $history['log_webhook_status_code'],
                    'event' => $history['log_webhook_event'],
                    'time' => $history['log_webhook_response_time'],
                    'req_header' => $history['log_webhook_request_header'],
                    'req_body' => $history['log_webhook_request_body'],
                    'res_header' => $history['log_webhook_response_header'],
                    'res_body' => $history['log_webhook_response_body'],
                    'res_view' => $showResponseView ? 'show' : 'hidden',
                ];
                $Tpl->add('history:loop', $data);
            }
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }

    function auth()
    {
        if (sessionWithAdministration()) {
            return true;
        }
        return false;
    }

    function edit(&$Tpl)
    {
        $id = $this->Get->get('id');
        $data = $this->Post->getChild('webhook');

        $sql = SQL::newSelect('webhook');
        $sql->addSelect('webhook_blog_id');
        $sql->addWhereOpr('webhook_id', $id);
        $bid = DB::query($sql->get(dsn()), 'one');

        if (empty($bid) || !sessionWithAdministration($bid)) {
            $Tpl->add('error#auth');
            return true;
        }

        if ($data->isNull()) {
            $_data = loadWebhook($id);
            if (!$_data->get('status')) {
                $_data->setField('status', 'open');
            }
            $data->overload($_data);
        }

        // webhook の タイプ選択肢を組み立て
        $types = configArray('webhook_types');
        $labels = configArray('webhook_types_label');
        foreach ($types as $i => $type) {
            $item = [
                'type_value' => $type,
                'type_label' => $labels[$i],
            ];
            if ($data->get('type') === $type) {
                $item['selected'] = config('attr_selected');
            }
            $Tpl->add('type_group:loop', $item);
        }
        if ($type = $data->get('type')) {
            $webhookEventValue = configArray("webhook_event_{$type}");
            $webhookEventLabel = configArray("webhook_event_{$type}_label");
            $events = explode(',', $data->get('events'));
            $labels = [];
            foreach ($webhookEventValue as $i => $value) {
                if (in_array($value, $events, true)) {
                    $labels[] = $webhookEventLabel[$i];
                }
            }
            $data->add('events-label', implode(',', $labels));
        }

        return true;
    }
}
