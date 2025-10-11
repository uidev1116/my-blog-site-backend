<?php

class ACMS_GET_Member_Sns extends ACMS_GET_Member
{
    /**
     * SNSログイン機能が有効か
     * @var bool
     */
    protected $enable = false;

    /**
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        if (config('snslogin') !== 'on') {
            return;
        }
        if (SUID && !snsLoginAuth(SUID)) {
            return;
        }
        if (SUID) {
            $user = loadUser(SUID);
        } else {
            $user = new Field_Validation();
        }
        $this->google($user, $tpl);
        $this->twitter($user, $tpl);
        $this->line($user, $tpl);

        $vars = $this->buildField($this->Post, $tpl);
        $tpl->add(null, $vars);
    }

    /**
     * Googleログインできるか判定
     *
     * @param Field_Validation $user
     * @param Template $tpl
     * @return void
     */
    protected function google(Field_Validation $user, Template $tpl): void
    {
        if (!config('google_login_client_id')) {
            return;
        }
        $googleid = $user->get('google_id');
        if (empty($googleid)) {
            $tpl->add(['google_notVerified', 'google']);
        } else {
            $tpl->add(['google_verified', 'google'], [
                'googleid' => $googleid,
            ]);
        }
        $tpl->add('google');
    }

    /**
     * Twitterログインできるか判定
     *
     * @param Field_Validation $user
     * @param Template $tpl
     * @return void
     */
    protected function twitter(Field_Validation $user, Template $tpl): void
    {
        if (!config('twitter_sns_login_consumer_key')) {
            return;
        }
        $twid = $user->get('twitter_id');
        if (empty($twid)) {
            $tpl->add(['tw_notVerified', 'twitter']);
        } else {
            $tpl->add(['tw_verified', 'twitter'], [
                'twid' => $twid,
            ]);
        }
        $tpl->add('twitter');
    }

    /**
     * LINEログインできるか判定
     *
     * @param Field_Validation $user
     * @param Template $tpl
     * @return void
     */
    protected function line(Field_Validation $user, Template $tpl): void
    {
        if (!config('line_app_id')) {
            return;
        }
        $lineid = $user->get('line_id');
        if (empty($lineid)) {
            $tpl->add(['line_notVerified', 'line']);
        } else {
            $tpl->add(['line_verified', 'line'], [
                'lineid' => $lineid,
            ]);
        }
        $tpl->add('line');
    }
}
