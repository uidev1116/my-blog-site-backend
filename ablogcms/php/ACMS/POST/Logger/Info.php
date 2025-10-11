<?php

class ACMS_POST_Logger_Info extends ACMS_POST
{
    public function post()
    {
        try {
            $id = $this->Post->get('id');
            $this->validate($id);

            $sql = SQL::newSelect('audit_log');
            $sql->addWhereOpr('audit_log_id', $id);
            $log = DB::query($sql->get(dsn()), 'row');
            if (empty($log)) {
                throw new \RuntimeException('ログが存在しません');
            }
            $data = $this->buildData($log);
            $data['success'] = true;
            Common::responseJson($data);
        } catch (\Exception $e) {
            Common::responseJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param int $id
     * @return void
     * @throws RuntimeException
     */
    protected function validate(int $id): void
    {
        if (!sessionWithAdministration()) {
            throw new \RuntimeException('アクセス権限がありません');
        }
        if (empty($id)) {
            throw new \RuntimeException('パラメータが間違っています');
        }
    }

    /**
     * ログデータを組み立て
     * @param array $log
     * @return array
     */
    protected function buildData(array $log): array
    {
        $suid = $log['audit_log_session_uid'];
        $data = [
            'id' => $log['audit_log_id'],
            'datetime' => $log['audit_log_datetime'],
            'level' => $log['audit_log_level'],
            'levelName' => $log['audit_log_level_name'],
            'suid' => $suid,
            'sessionUserName' => ACMS_RAM::userName($suid),
            'sessionUserMail' => ACMS_RAM::userMail($suid),
            'bid' => $log['audit_log_blog_id'],
            'blogName' => ACMS_RAM::blogName($log['audit_log_blog_id']),
            'message' => $log['audit_log_message'],
            'url' => $log['audit_log_url'],
            'isManagedDomain' => $this->isManagedDomain($log['audit_log_url']),
            'ua' => $log['audit_log_ua'],
            'referer' => $log['audit_log_referer'],
            'ipAddress' => $log['audit_log_addr'],
            'method' => $log['audit_log_method'],
            'httpStatus' => $log['audit_log_status'],
            'responseTime' => $log['audit_log_response_time'],
            'eid' => $log['audit_log_eid'],
            'cid' => $log['audit_log_cid'],
            'uid' => $log['audit_log_uid'],
            'rid' => $log['audit_log_rid'],
            'ruleName' => ACMS_RAM::ruleName($log['audit_log_rid']),
            'extra' => $log['audit_log_extra'],
            'context' => json_decode($log['audit_log_context']),
        ];
        if ($altUid = $log['audit_log_session_alt_uid']) {
            $data['switchUserName'] = ACMS_RAM::userName($altUid);
            $data['switchUserMail'] = ACMS_RAM::userMail($altUid);
        }
        if (intval($log['audit_log_level']) >= 250) {
            $data['reqHeader'] = json_decode($log['audit_log_req_header']);
            $data['reqBody'] = json_decode($log['audit_log_req_body']);
        }
        if ($post = $log['audit_log_acms_post']) {
            $data['acmsPost'] = $post;
        }
        return $data;
    }

    /**
     * 指定されたURLがCMSで管理しているドメインか判定する
     *
     * @param string $url
     * @return bool
     */
    protected function isManagedDomain(string $url): bool
    {
        if (empty($url)) {
            return false;
        }
        $domain = parse_url($url, PHP_URL_HOST);
        if (empty($domain)) {
            return false;
        }
        $sql = SQL::newSelect('blog');
        $sql->addWhereOpr('blog_domain', $domain);
        if (!DB::query($sql->get(dsn()), 'row')) {
            $sql = SQL::newSelect('alias');
            $sql->addWhereOpr('alias_domain', $domain);
            $sql->addWhereOpr('alias_status', 'open');
            if (!DB::query($sql->get(dsn()), 'row')) {
                return false;
            }
        }
        return true;
    }
}
