<?php

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;

class ACMS_POST
{
    /**
     * @var bool
     */
    public $isCacheDelete = true;

    /**
     * @var Field
     */
    public $Q;

    /**
     * @var Field
     */
    public $Get;

    /**
     * @var \Field_Validation
     */
    public $Post;

    /**
     * @var bool
     */
    protected $isCSRF = true;

    /**
     * @var bool
     */
    protected $checkDoubleSubmit = false;

    /**
     * @var \Field_Validation
     */
    protected $systemErrors;

    /**
     * @var \Field_Validation
     */
    protected $errors;

    /**
     * @var \Field_Validation
     */
    protected $messages;

    /**
     * CSRFトークンの存在チェック
     *
     * @return boolean
     */
    protected function csrfTokenExists()
    {
        return Common::csrfTokenExists();
    }

    /**
     * CSRFトークンのチェック
     *
     * @return boolean
     */
    protected function checkCsrfToken()
    {
        return Common::checkCsrfToken($this->Post->get('formToken'));
    }

    protected function shouldCheckDoubleSubmit()
    {
        if (!$this->Post->isExists('formUniqueToken')) {
            return false;
        }

        if (!$this->checkDoubleSubmit) {
            return false;
        }

        return true;
    }

    /**
     * 二重送信のチェック
     *
     * @return boolean
     */
    protected function checkDoubleSubmit()
    {
        $session = Session::handle();
        $sessionFormUniqueToken = $session->get('formUniqueToken');
        $session->set('formUniqueToken', $this->Post->get('formUniqueToken'));
        $session->save();

        if ($this->Post->get('formUniqueToken') === $sessionFormUniqueToken) {
            return false;
        }
        return true;
    }

    /**
     * システムエラーの表示登録
     *
     * @param string $block
     */
    protected function addSystemError($block)
    {
        if (empty($block)) {
            return;
        }
        $this->systemErrors->addField('error', $block);
        $this->Post->addChild('systemErrors', $this->systemErrors);
        $this->Post->setMethod('system', 'error', false);
        $this->Post->validate(new ACMS_Validator());
    }

    /**
     * エラーメッセージの表示登録
     *
     * @param string $message
     */
    protected function addError($message)
    {
        if (empty($message)) {
            return;
        }
        $this->errors->addField('error', $message);

        $this->Post->addChild('errors', $this->errors);

        $session =& Field::singleton('session');
        $session->addChild('errors', $this->errors);
    }

    /**
     * 管理画面に出力するメッセージを追加
     *
     * @param string $message
     */
    protected function addMessage($message)
    {
        if (empty($message)) {
            return;
        }
        $this->messages->addField('message', $message);
        $this->Post->addChild('messages', $this->messages);

        $session =& Field::singleton('session');
        $session->addChild('messages', $this->messages);
    }

    /**
     * キャッシュクリアが必要かどうかを判断
     * @return bool
     */
    protected function shouldClearCache(): bool
    {
        if (!$this->isCacheDelete) {
            return false;
        }
        if (config('cache_clear_when_post') !== 'on') {
            return false;
        }
        return true;
    }

    /**
     * @return Field_Validation
     */
    public function fire()
    {
        $app = App::getInstance();
        assert($app instanceof \Acms\Application);
        $this->Q =& $app->getQueryParameter();
        $this->Get =& $app->getGetParameter();
        $this->Post =& $app->getPostParameter();
        $this->systemErrors = new Field_Validation();
        $this->errors = new Field_Validation();
        $this->messages = new Field_Validation();

        //----------
        // takeover
        if ($takeover = $this->Post->get('takeover')) {
            $Post = acmsUnserialize($takeover);
            if ($Post instanceof Field) {
                $Post->reset(true);
                $this->Post->deleteField('takeover');
                $Post->overload($this->Post, true);
                $this->Post = new Field_Validation($Post, true);
            } else {
                httpStatusCode('400 Bad Request');
                AcmsLogger::error('POSTデータの「takeover」が復元できません');
                return $this->Post;
            }
        }

        //-------------------------
        // Check missing POST data
        if (!$this->Post->isExists('formToken')) {
            AcmsLogger::error('POSTデータが不完全である可能性があるため処理を中断しました');
            $this->addSystemError('IllegalPostData');
            return $this->Post;
        }

        //------------
        // CSRF Check
        if ($this->isCSRF) {
            if (!$this->csrfTokenExists()) {
                AcmsLogger::notice('セッションにCSRFトークンが存在しないため、処理を中断しました');
                $this->addSystemError('CsrfTokenExpired');
                return $this->Post;
            }
            if ($this->shouldCheckDoubleSubmit() && !$this->checkDoubleSubmit()) {
                AcmsLogger::notice('重複送信を検知したため、処理を中断しました');
                $this->addSystemError('DoubleTransmission');
                return $this->Post;
            }
            if (!$this->checkCsrfToken()) {
                AcmsLogger::notice('POSTされたCSRFトークンとセッションのCSRFトークンが一致しないため、処理を中断しました');
                $this->addSystemError('IllegalAccess');
                return $this->Post;
            }
            if (isCSRF()) {
                AcmsLogger::notice('管理ドメイン以外のリファラーのため、処理を中断しました（' . REFERER . '）');
                $this->addSystemError('IllegalAccess');
                return $this->Post;
            }
        }

        //---------
        // extract
        foreach ($this->Post->listFields() as $fd) {
            if (!preg_match('/(.+):field/u', $fd, $match)) {
                continue;
            }
            $field = $match[1];
            $validator = $this->Post->get($fd);
            $this->Post->deleteField($fd);
            $V = $validator instanceof \ACMS_Validator ? (new $validator()) : null;
            $this->extract($field, $V);
        }

        //----------------
        // register cache clear
        if ($this->shouldClearCache()) {
            register_shutdown_function(function () {
                // モジュールの処理内でリダイレクトやdie()された場合を考慮して、
                // register_shutdown_function()でページキャッシュをクリアする。
                ACMS_POST_Cache::clearPageCache(BID);
            });
        }

        //----------------
        // execute & hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('beforePostFire', [$this]);
            $res = $this->post();
            $Hook->call('afterPostFire', [$this]);
        } else {
            $res = $this->post();
        }

        define('ACMS_POST_VALID', $this->Post->isValidAll() ? 'true' : 'false');

        return $res;
    }

    /**
     * @return Field_Validation|never
     */
    public function post()
    {
        throw new \BadMethodCallException('Method post() is not implemented.');
    }

    public function redirect($url = null, $sid = null, $auth = false)
    {
        redirect($url);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @return string
     */
    public function archivesDir()
    {
        return LocalStorage::archivesDir();
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param string $path
     * @param int $mod 未使用の引数
     * @return bool
     */
    public function setupDir($path, $mod)
    {
        return LocalStorage::makeDirectory($path);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param string $dir
     * @return bool
     */
    public function removeDir($dir)
    {
        return LocalStorage::removeDirectory($dir);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * @param string $scp
     * @param \ACMS_Validator|null $V
     * @param \Field|null $deleteField
     * @return \Field_Validation
     */
    public function extract($scp = 'field', $V = null, &$deleteField = null): Field_Validation
    {
        $field = Common::extract($scp, $V, $deleteField);
        $deleteField = Common::getDeleteField();

        return $field;
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param \Field $Post
     * @return \Field
     */
    public function getUriObject($Post)
    {
        return Common::getUriObject($Post);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param int $len パスワードの長さ
     * @return string
     */
    public function genPass($len)
    {
        return Common::genPass($len);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param array{
     *   smtp-host?: string,
     *   smtp-port?: string,
     *   smtp-user?: string,
     *   smtp-pass?: string,
     *   smtp-verify-peer?: string,
     *   mail_from?: string,
     *   sendmail_path?: string,
     *   additional_headers?: string
     * } $argConfig
     *
     * @return non-empty-array<'additional_headers'|'mail_from'|'sendmail_path'|'smtp-google'|'smtp-google-user'|'smtp-host'|'smtp-pass'|'smtp-verify-peer'|'smtp-port'|'smtp-user',
     *   string
     * >
     */
    public function mailConfig($argConfig = [])
    {
        return Common::mailConfig($argConfig);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param string $tplFile
     * @param Field $Field
     * @param string|null $charset
     *
     * @return string
     */
    public function getMailTxt($tplFile, $Field, $charset = null)
    {
        return Common::getMailTxt($tplFile, $Field, $charset);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param int $eid
     * @return string
     */
    public function loadEntryFulltext($eid)
    {
        return Common::loadEntryFulltext($eid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param int $uid
     * @return string
     */
    public function loadUserFulltext($uid)
    {
        return Common::loadUserFulltext($uid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param int $cid
     * @return string
     */
    public function loadCategoryFulltext($cid)
    {
        return Common::loadCategoryFulltext($cid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param int $bid
     * @return string
     */
    public function loadBlogFulltext($bid)
    {
        return Common::loadBlogFulltext($bid);
    }

    /**
     * 位置情報を保存する
     * @param string $type
     * @param int $id
     * @param Field|null $Field
     * @param int|null $rvid
     *
     * @return void
     */
    public function saveGeometry($type, $id, $Field = null, $rvid = null)
    {
        if (empty($type) || empty($id)) {
            return;
        }

        $table = 'geo';
        if ($rvid) {
            $table = "geo_rev";
        }

        $DB = DB::singleton(dsn());

        $SQL = SQL::newDelete($table);
        $SQL->addWhereOpr('geo_' . $type, $id);
        if ($rvid) {
            $SQL->addWhereOpr('geo_rev_id', $rvid);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        if (is_null($Field)) {
            return;
        }

        if (!$Field->get('geo_lat') || !$Field->get('geo_lng')) {
            return;
        }
        $SQL = SQL::newInsert($table);
        $SQL->addInsert('geo_geometry', SQL::newGeometry($Field->get('geo_lat'), $Field->get('geo_lng')));
        $SQL->addInsert('geo_zoom', intval($Field->get('geo_zoom')));
        $SQL->addInsert('geo_' . $type, $id);
        $SQL->addInsert('geo_blog_id', BID);
        if ($rvid) {
            $SQL->addInsert('geo_rev_id', $rvid);
        }
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * カスタムフィールドを保存する
     * @param string $type
     * @param int $id
     * @param Field|null $Field
     * @param Field|null $deleteField
     * @param int|null $rvid
     *
     * @return bool
     */
    public function saveField($type, $id, $Field = null, $deleteField = null, $rvid = null)
    {
        return Common::saveField($type, $id, $Field, $deleteField, $rvid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     * @deprecated
     * @param string $type フルテキストのタイプ
     * @param int $id
     * @param string|null $fulltext
     *
     * @return bool
     */
    public function saveFulltext($type, $id, $fulltext = null)
    {
        Common::saveFulltext($type, $id, $fulltext);
        return true;
    }

    /**
     * ワークフローのデータをPostから抽出する
     * @return Field_Validation
     */
    protected function extractWorkflow()
    {
        $workflow = $this->extract('workflow');

        $status = $workflow->get('workflow_status');
        if ($status === 'open') {
            $workflow->setMethod('workflow_name', 'required');
            $workflow->setMethod('workflow_type', 'required');
            $workflow->setMethod('workflow_role', 'operable', sessionWithEnterpriseAdministration());

            // 並列チェック
            if ($workflow->get('workflow_type') === 'parallel') {
                $workflow->setMethod('workflow_route_group', 'required');
                $workflow->setMethod('workflow_public_point', 'required');
                $workflow->setMethod('workflow_reject_point', 'required');

                // 直列チェック
            } else {
                $workflow->setMethod('workflow_start_group', 'required');
                $workflow->setMethod('workflow_last_group', 'required');

                $groups = [];
                if ($workflow->get('workflow_start_group')) {
                    $groups = array_merge($groups, $workflow->getArray('workflow_start_group'));
                }
                if ($workflow->get('workflow_last_group')) {
                    $groups = array_merge($groups, $workflow->getArray('workflow_last_group'));
                }
                if ($routeFlow = $workflow->getArray('workflow_route_group')) {
                    foreach ($routeFlow as $i => $ugid) {
                        $groups[] = $ugid;
                    }
                }

                $overlap = array_count_values($groups);
                foreach ($overlap as $i => $val) {
                    if ($val > 1) {
                        $workflow->setMethod('workflow', 'unique', false);
                    }
                }
            }
            $workflow->validate(new ACMS_Validator());
        }
        return $workflow;
    }

    /**
     * ワークフローのデータを保存する
     * @param \Field $workflow
     * @param int $bid
     * @param int|null $cid
     * @return void
     */
    protected function saveWorkflow($workflow, $bid, $cid = null)
    {
        $DB = DB::singleton(dsn());

        //-----------
        // workgorup
        $SQL = SQL::newDelete('workflow');
        $SQL->addWhereOpr('workflow_blog_id', $bid);
        $SQL->addWhereOpr('workflow_category_id', $cid);
        $DB->query($SQL->get(dsn()), 'exec');

        $SQL = SQL::newInsert('workflow');
        foreach ($workflow->listFields() as $key) {
            if (
                1
                && $key !== 'workflow_route_group'
                && $key !== 'route_approval_number'
                && $key !== '@workflowGroup'
            ) {
                $value = implode(',', $workflow->getArray($key));
                $SQL->addInsert($key, $value);
            }
        }
        $SQL->addInsert('workflow_blog_id', $bid);
        if ($cid) {
            $SQL->addInsert('workflow_category_id', $cid);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        //---------------------------
        // workgroup route usergroup
        $SQL = SQL::newDelete('workflow_usergroup');
        $SQL->addWhereOpr('workflow_blog_id', $bid);
        $SQL->addWhereOpr('workflow_category_id', $cid);
        $DB->query($SQL->get(dsn()), 'exec');

        if ($routeFlow = $workflow->getArray('workflow_route_group')) {
            foreach ($routeFlow as $i => $ugid) {
                if (empty($ugid)) {
                    continue;
                }
                $SQL = SQL::newInsert('workflow_usergroup');
                $SQL->addInsert('workflow_blog_id', $bid);
                if ($cid) {
                    $SQL->addInsert('workflow_category_id', $cid);
                }
                $SQL->addInsert('usergroup_id', $ugid);
                $SQL->addInsert('workflow_sort', $i + 1);
                $DB->query($SQL->get(dsn()), 'exec');
            }
        }

        $this->Post->set('edit', 'update');
    }

    /**
     * エイリアスのスコープが正しいか判定
     * @param string $scope
     * @return bool
     */
    protected function checkScope($scope = 'local')
    {
        $DB = DB::singleton(dsn());
        do {
            if ($scope !== 'global') {
                return true;
            }
            //-----------
            // blog code
            $SQL = SQL::newSelect('blog');
            $SQL->addWhereOpr('blog_code', '');
            ACMS_Filter::blogTree($SQL, BID, 'descendant');
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }
            //-------------------
            // overlap blog code
            $SQL = SQL::newSelect('blog');
            $SQL->addSelect('blog_code');
            ACMS_Filter::blogTree($SQL, BID, 'descendant');
            $SQL->addGroup('blog_code');
            $SQL->addHaving(SQL::newOpr('*', 1, '>', null, 'COUNT'));
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }
        } while (false);

        return true;
    }

    /**
     * コンフィグセットのスコープが正しいか判定
     * @param int $setid
     * @return bool
     */
    protected function checkConfigSetScope($setid)
    {
        if (empty($setid)) {
            return true;
        }
        $SQL = SQL::newSelect('config_set');
        $SQL->addSelect('config_set_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'config_set_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');
        $Where = SQL::newWhere();
        $Where->addWhereOpr('config_set_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('config_set_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $SQL->addWhereOpr('config_set_id', $setid);

        if (DB::query($SQL->get(dsn()), 'one')) {
            return true;
        }
        return false;
    }
}
