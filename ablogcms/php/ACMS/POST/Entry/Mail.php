<?php

use Acms\Services\PageGeneration\PageGenerationService;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;

class ACMS_POST_Entry_Mail extends ACMS_POST_Entry
{
    public $isCacheDelete = false;

    /**
     * メール送信設定（PC/モバイル）
     *
     * @var array<int, array{mail: string, magazine: string, html: bool}>
     */
    private const MAIL_CONFIGS = [
        [
            'mail' => 'user_mail',
            'magazine' => 'user_mail_magazine',
            'html' => true,
        ],
        [
            'mail' => 'user_mail_mobile',
            'magazine' => 'user_mail_mobile_magazine',
            'html' => false,
        ],
    ];

    /**
     * @return Field_Validation
     */
    public function post()
    {
        @set_time_limit(0);

        $validated = $this->validateRequest();
        if ($validated === null) {
            return $this->Post;
        }
        [$eid] = $validated;

        Session::handle()->writeClose(); // セッションをクローズ（デッドロック対応）

        $userBid = $this->Post->get('user_blog_id') > 0
            ? (int) $this->Post->get('user_blog_id')
            : BID;

        $subject = $this->fetchTemplateContent($eid, 'mail_entry_tpl_subject');
        if ($subject === null || $subject === '') {
            return $this->Post;
        }
        if (!LICENSE_PLUGIN_MAILMAGAZINE) {
            $subject = '[test]' . $subject;
        }

        $plain = $this->fetchTemplateContent($eid, 'mail_entry_tpl_body_plain');
        if ($plain === null || $plain === '') {
            return $this->Post;
        }

        $html = $this->fetchTemplateContent($eid, 'mail_entry_tpl_body_html');

        foreach (self::MAIL_CONFIGS as $mailConfig) {
            $bccChunks = $this->buildBccChunks($mailConfig, $userBid);
            foreach ($bccChunks as $bccList) {
                $this->sendMail($eid, $subject, $plain, $html, $bccList, $mailConfig['html']);
            }
        }

        return $this->Post;
    }

    /**
     * リクエストを検証し、bid と eid を返す。不正な場合は null
     *
     * @return array{0: int}|null
     */
    protected function validateRequest(): ?array
    {
        // グローバル定数・セッションのため静的解析で誤検知される
        /** @phpstan-ignore booleanNot.alwaysTrue, booleanOr.alwaysTrue */
        if (!ACMS_SID || !sessionWithAdministration()) {
            return null;
        }
        $entryId = (int)$this->Post->get('eid');
        if ($entryId === 0) {
            return null;
        }
        $blogId = ACMS_RAM::entryBlog((int) $entryId);
        if (!isBlogAncestor((int) $blogId, SBID, true)) {
            return null;
        }
        return [$entryId];
    }

    /**
     * テンプレート設定キーでメール用テンプレート本文を取得する
     *
     * @param int $entryId
     * @param string $tplConfigKey
     * @return string|null 取得失敗時は null、空の場合は ''
     */
    protected function fetchTemplateContent(int $entryId, string $tplConfigKey): ?string
    {
        $url = acmsLink([
            'eid' => $entryId,
            'tpl' => config($tplConfigKey),
        ], [
            'explicitTpl' => true,
            'ignoreTplIfAjax' => false,
        ]);
        if ($url === false) {
            return null;
        }
        try {
            return $this->getTemplateFromUrl($url);
        } catch (\Throwable $e) {
            AcmsLogger::warning('メールテンプレートの取得に失敗しました', Common::exceptionArray($e, ['url' => $url]));
            $this->addError($e->getMessage());
            return null;
        }
    }

    /**
     * BCC用のメールアドレスをチャンク単位で取得する（送信数制限対応）
     *
     * @param array{mail: string, magazine: string, html: bool} $mailConfig
     * @param int $userBid
     * @return array<int, array<int, string>>
     */
    protected function buildBccChunks(array $mailConfig, int $userBid): array
    {
        if (!$this->Post->get('issue') || !LICENSE_PLUGIN_MAILMAGAZINE) {
            return [[]];
        }

        $bccLimit = (int) config('mail_entry_bcc_limit');
        $SQL = SQL::newSelect('user');
        $SQL->setSelect($mailConfig['mail']);
        $SQL->addWhereOpr($mailConfig['magazine'], 'on');
        $SQL->addWhereOpr('user_status', 'open');
        $SQL->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');

        $shouldRegistered = SQL::newWhere();
        $shouldRegistered->addWhereOpr('user_auth', 'subscriber', '!=', 'OR');
        $shouldRegistered->addWhereOpr('user_pass', '', '!=', 'OR');
        $SQL->addWhere($shouldRegistered);
        $SQL->addWhereOpr('user_blog_id', $userBid);

        $DB = DB::singleton(dsn());
        $rows = $DB->query($SQL->get(dsn()), 'all');

        $chunks = [];
        $chunkIndex = 0;
        $indexInChunk = 0;
        foreach ($rows as $row) {
            $address = $row[$mailConfig['mail']] ?? '';
            if ($address === '') {
                continue;
            }
            if (!isset($chunks[$chunkIndex])) {
                $chunks[$chunkIndex] = [];
            }
            $chunks[$chunkIndex][$indexInChunk] = $address;
            $indexInChunk++;
            if ($indexInChunk >= $bccLimit) {
                $chunkIndex++;
                $indexInChunk = 0;
            }
        }
        return $chunks !== [] ? $chunks : [[]];
    }

    /**
     * メールを1通送信する
     *
     * @param int $eid
     * @param string $subject
     * @param string $plain
     * @param string|null $html
     * @param array<int, string> $bccList
     * @param bool $useHtml
     */
    protected function sendMail(int $eid, string $subject, string $plain, ?string $html, array $bccList, bool $useHtml): void
    {
        $to = implode(', ', configArray('mail_entry_to'));
        if ($to === '') {
            $fallbackTo = ACMS_RAM::userMail(SUID); // @phpstan-ignore-line
            $to = $fallbackTo !== null && $fallbackTo !== '' ? $fallbackTo : '';
        }
        $from = config('mail_entry_from');

        try {
            $mailer = Mailer::init()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($plain);

            if ($useHtml && $html !== null && $html !== '') {
                $mailer = $mailer->setHtml($html);
            }
            if ($bccList !== []) {
                $mailer->setBcc(implode(',', $bccList));
            }
            $mailer->send();

            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーのメールマガジンを送信しました', [
                'to' => $to,
                'from' => $from,
                'subject' => $subject,
                'bcc' => $bccList,
            ]);
        } catch (\Throwable $e) {
            AcmsLogger::warning('メールマガジンの送信に失敗しました', Common::exceptionArray($e, ['entryTitle' => ACMS_RAM::entryTitle($eid)]));
        }
    }

    /**
     * メールテンプレートを取得する
     *
     * @param string $url
     * @return string
     */
    protected function getTemplateFromUrl(string $url): string
    {
        $pageGenerationService = new PageGenerationService();
        $pageGenerationService->addPage(url: $url, destinationPathname: 'get_template', userAgent: 'acms', withSession: true);
        $results = $pageGenerationService->run(maxParallel: 1, listener: null, withData: true);
        if (!isset($results[0])) {
            throw new RuntimeException('メールテンプレートの取得に失敗しました');
        }
        $result = $results[0];
        $data = $result->getData();

        if ($result->isSuccess() && $result->getStatusCode() === 200 && $data !== null && $data !== '') {
            $charset = mb_detect_encoding($data, 'UTF-8, EUC-JP, SJIS-win, SJIS');
            if ($charset && 'UTF-8' !== $charset) {
                $data = mb_convert_encoding($data, 'UTF-8', $charset);
            }
            if ($data === false) {
                return '';
            }
            return $data;
        }
        throw new RuntimeException('メールテンプレートの取得に失敗しました');
    }
}
