<?php

namespace Acms\Services\Preview;

use Acms\Services\Preview\Contracts\Base;
use Acms\Services\Facades\Common;
use Session;
use App;
use DB;
use SQL;
use RuntimeException;

class Engine implements Base
{
    /**
     * プレビュー共有ページのADMIN
     */
    protected const PREVIEW_SHARE_ADMIN = 'preview_share';

    /**
     * プレビュー共有ページのテンプレート
     */
    protected const PREVIEW_SHARE_TPL = 'admin/preview/share.html';

    /**
     * PHPセッションラッパー
     *
     * @var \Session
     */
    protected $session;

    /**
     * @var \Field
     */
    protected $get;

    /**
     * プレビュー共有URLの有効時間
     *
     * @var int
     */
    protected $lifetime;

    /**
     * 共有URL
     *
     * @var string
     */
    protected $shareUrl;

    /**
     * 偽装UAをセッションに保存する時のキー名
     *
     * @var string
     */
    protected $previewFakeUaKeyName = 'preview_fake_ua';

    /**
     *  偽装UAをセッションを確認するためのトークンのキー名
     *
     * @var string
     */
    protected $previewFakeUaTokenKeyName = 'preview_fake_ua_token';

    /**
     * プレビュー共有するための認証トークンのキー名
     *
     * @var string
     */
    protected $previewShareUrlTokenKeyName = 'preview-token';

    /**
     * プレビューモードか判定するために使うURLクエリパラメーター名
     *
     * @var string
     */
    protected $previewModeQueryParameter = 'acms-preview-mode';

    /**
     * Engine constructor.
     *
     * @param int $lifetime
     * @param string $shareUrl
     */
    public function __construct($lifetime, $shareUrl)
    {
        $app = App::getInstance();
        assert($app instanceof \Acms\Application);
        $this->get = $app->getGetParameter();
        $this->lifetime = $lifetime;
        $this->shareUrl = $shareUrl;
    }

    /**
     * プレビューモード中か判定
     *
     * @return bool
     */
    public function isPreviewMode()
    {
        if ($session = $this->getSession()) {
            $fakeUaToken = $session->get($this->previewFakeUaTokenKeyName, false);
            if ($fakeUaToken && $fakeUaToken === $this->get->get($this->previewModeQueryParameter)) {
                setConfig('x_frame_options', 'off');
                return true;
            }
        }
        if ($this->isValidPreviewSharingUrl()) {
            setConfig('x_frame_options', 'off');
            return true;
        }
        return false;
    }

    /**
     * 偽装ユーザーエージェントの取得
     *
     * @return string|false
     */
    public function getFakeUserAgent()
    {
        if ($this->isPreviewMode()) {
            $session = $this->getSession();
            return $session->get('preview_fake_ua', false);
        }
        return false;
    }

    /**
     * プレビュー共有モードになれるか判定
     *
     * @return bool
     */
    public function isValidPreviewSharingUrl()
    {
        $previewToken = $this->get->get($this->previewShareUrlTokenKeyName);
        if (empty($previewToken)) {
            return false;
        }
        $SQL = SQL::newSelect('preview_share');
        $SQL->addSelect('preview_share_uri');
        $SQL->addWhereOpr('preview_share_expire', date('Y-m-d H:i:s', REQUEST_TIME), '>=');
        $SQL->addWhereOpr('preview_share_token', $previewToken);
        $url = DB::query($SQL->get(dsn()), 'one');
        if (empty($url)) {
            return false;
        }
        $isPreview = $this->shareUrlFormat(REQUEST_URL) === $url || (is_ajax() && $this->shareUrlFormat(htmlspecialchars_decode(REFERER, ENT_QUOTES)) === $url);
        if ($isPreview) {
            $session = Session::handle();
            $session->set('in-preview', REQUEST_TIME + (60 * 15));
            $session->save();
        }
        return $isPreview;
    }


    /**
     * プレビュー共有URLの取得
     *
     * @param string $url
     * @return string
     */
    public function getShareUrl($url, $lifetime = false)
    {
        if (!Common::isSafeUrl($url)) {
            throw new RuntimeException('The provided URL is not safe.');
        }
        $token = uniqueString() . uniqueString();
        if (empty($lifetime)) {
            $lifetime = $this->lifetime;
        }
        $SQL = SQL::newInsert('preview_share');
        $SQL->addInsert('preview_share_uri', $this->shareUrlFormat($url));
        $SQL->addInsert('preview_share_expire', date('Y-m-d H:i:s', REQUEST_TIME + $lifetime));
        $SQL->addInsert('preview_share_token', $token);
        DB::query($SQL->get(dsn()), 'exec');

        return $this->shareUrl . "?token={$token}";
    }

    /**
     * 共有URLで実際に表示するiFrameのURL
     *
     * @return string
     */
    public function getSharePreviewUrl()
    {
        $token = $this->get->get('token');
        if (empty($token)) {
            throw new RuntimeException('Empty token');
        }
        $SQL = SQL::newSelect('preview_share');
        $SQL->addSelect('preview_share_uri');
        $SQL->addWhereOpr('preview_share_expire', date('Y-m-d H:i:s', REQUEST_TIME), '>=');
        $SQL->addWhereOpr('preview_share_token', $token);

        if ($url = DB::query($SQL->get(dsn()), 'one')) {
            $query = parse_url($url, PHP_URL_QUERY);
            if ($query) {
                $url .= "&preview-token={$token}";
            } else {
                $url .= "?preview-token={$token}";
            }
            return $url;
        }
        throw new RuntimeException('Failed get preview url.');
    }

    /**
     * 期限切れの共有URLを削除
     */
    public function expiredShareUrl()
    {
        $SQL = SQL::newDelete('preview_share');
        $SQL->addWhereOpr('preview_share_expire', date('Y-m-d H:i:s', REQUEST_TIME - 1), '<');
        DB::query($SQL->get(dsn()), 'exec');
    }

    /**
     * プレビューモードを開始
     *
     * @param string $fakeUserAgent
     * @param string $token
     * @return void
     */
    public function startPreviewMode($fakeUserAgent, $token)
    {
        if ($session = $this->getSession()) {
            if ($fakeUserAgent && $token) {
                $session->set($this->previewFakeUaKeyName, $fakeUserAgent);
                $session->set($this->previewFakeUaTokenKeyName, $token);
            } else {
                $session->delete($this->previewFakeUaKeyName);
                $session->delete($this->previewFakeUaTokenKeyName);
            }
            $session->save();
        }
    }

    /**
     * プレビューモードを終了
     *
     * @return void
     */
    public function endPreviewMode()
    {
        if ($session = $this->getSession()) {
            $session->delete($this->previewFakeUaKeyName);
            $session->delete($this->previewFakeUaTokenKeyName);
            $session->save();
        }
    }

    /**
     * 共有URLから余分な文字列を削除
     *
     * @param string $url
     * @return string
     */
    protected function shareUrlFormat($url): string
    {
        $url = htmlspecialchars_decode($url, ENT_QUOTES);
        return preg_replace('/(\?|&)(acms-preview-mode|timestamp|preview-token)=[^&]+/', '', $url) ?? '';
    }

    /**
     * @return bool|mixed
     */
    private function getSession()
    {
        if ($this->session) {
            return $this->session;
        }
        if (sessionWithContribution() || ACMS_POST === 'Preview_Mode' || isset($_GET['acms-preview-mode'])) {
            $this->session = Session::handle();
            return $this->session;
        }
        return false;
    }

    /**
     * @param string $admin
     * @return bool
     */
    public function isPreviewShareAdmin(string $admin): bool
    {
        return $admin === self::PREVIEW_SHARE_ADMIN;
    }

    /**
     * @return string
     */
    public function getPreviewShareTpl(): string
    {
        return self::PREVIEW_SHARE_TPL;
    }
}
