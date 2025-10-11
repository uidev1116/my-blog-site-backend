<?php

namespace Acms\Services\Logger\Handler;

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Application;
use Monolog\Handler\AbstractProcessingHandler;
use Session;
use SQL;

class DatabaseHandler extends AbstractProcessingHandler
{
    /**
     * ログをデータベースに保存
     * @param array $record
     * @return void
     */
    protected function write(array $record): void
    {
        $dbExceptionSetting = DB::getThrowException();
        try {
            if (!editionWithProfessional() && $record['level'] === 200) {
                return;
            }
            DB::setThrowException(true);

            /** @var int $bid */
            $bid = defined('BID') ? BID : 0;
            /** @var int|null $uid */
            $uid = defined('UID') ? UID : null;
            /** @var int|null $cid */
            $cid = defined('CID') ? CID : null;
            /** @var int|null $eid */
            $eid = defined('EID') ? EID : null;
            /** @var int|null $rid */
            $rid = defined('RID') ? RID : null;
            /** @var int $suid */
            $suid = (defined('SUID') && !is_null(SUID)) ? SUID : 0;
            $acmsPost = (defined('ACMS_POST') && !!ACMS_POST) ? ACMS_POST : '';

            $sessionUserId = $suid;
            $altUserId = null;
            if (!empty($suid)) {
                $session = Session::handle();
                if ($originUid = $session->get(ACMS_LOGIN_SESSION_ORGINAL_UID)) {
                    $sessionUserId = $originUid;
                    $altUserId = $suid;
                }
            }

            if (defined('REQUEST_URL') && defined('REMOTE_ADDR') && defined('START_TIME')) {
                $id = DB::query(SQL::nextval('audit_log_id', dsn()), 'seq', true, false);

                $sql = SQL::newInsert('audit_log');
                $sql->addInsert('audit_log_id', $id);
                $sql->addInsert('audit_log_datetime', date('Y-m-d H:i:s', $record['datetime']->format('U')));
                $sql->addInsert('audit_log_level', $record['level']);
                $sql->addInsert('audit_log_level_name', $record['level_name']);
                $sql->addInsert('audit_log_url', REQUEST_URL);
                $sql->addInsert('audit_log_ua', UA);
                $sql->addInsert('audit_log_addr', REMOTE_ADDR);
                $sql->addInsert('audit_log_referer', REFERER);
                $sql->addInsert('audit_log_method', $_SERVER['REQUEST_METHOD']);
                $sql->addInsert('audit_log_status', httpStatusCode());
                $sql->addInsert('audit_log_response_time', sprintf('%0.6f', microtime(true) - START_TIME));
                $sql->addInsert('audit_log_acms_post', $acmsPost);
                $sql->addInsert('audit_log_message', $record['message']);
                $sql->addInsert('audit_log_session_uid', $sessionUserId);
                $sql->addInsert('audit_log_eid', $eid);
                $sql->addInsert('audit_log_cid', $cid);
                $sql->addInsert('audit_log_uid', $uid);
                $sql->addInsert('audit_log_rid', $rid);
                $sql->addInsert('audit_log_context', limitedJsonEncode($record['context']));
                $sql->addInsert('audit_log_extra', limitedJsonEncode($record['extra']));
                $sql->addInsert('audit_log_blog_id', $bid);
                if ($altUserId) {
                    $sql->addInsert('audit_log_session_alt_uid', $altUserId);
                }
                if (intval($record['level']) >= 250) {
                    $filter = Application::make('acms-logger-filter');
                    $data = $filter->getSafeArray($_POST);
                    $sql->addInsert('audit_log_req_header', limitedJsonEncode(getallheaders()));
                    $sql->addInsert('audit_log_req_body', limitedJsonEncode($data));
                }
                DB::query($sql->get(dsn()), 'exec', true, false);
            }
        } catch (\Exception $e) {
        }
        try {
            DB::setThrowException($dbExceptionSetting);
        } catch (\Exception $e) {
        }
    }
}
