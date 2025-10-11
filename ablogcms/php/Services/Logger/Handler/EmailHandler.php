<?php

namespace Acms\Services\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Acms\Services\Logger\Level;
use Acms\Services\Facades\Mailer;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Application;
use Field;
use Tpl;
use ACMS_Corrector;
use ACMS_RAM;
use RuntimeException;

class EmailHandler extends AbstractProcessingHandler
{
    /**
     * ログをメールで送信
     * @param array $record
     * @return void
     */
    protected function write(array $record): void
    {
        try {
            $lockFilePath = CACHE_DIR . 'logger-lock';
            $countFilePath = CACHE_DIR . 'logger-notify-count';

            $limit = intval(env('ALERT_COUNT_TO_PAUSE', 10));
            $timeRange = intval(env('ALERT_COUNT_PER_MINUTE', 3));
            $stopTime = intval(env('ALERT_PAUSE_TIME', 60));

            if (!$this->suppressNotifications($lockFilePath, $countFilePath, $limit, $timeRange, $stopTime)) {
                return; // メール通知は一時停止中
            }
            $field = new Field();
            $field->addField('channel', $record['channel']);
            $field->addField('level', Level::getLevelNameJa(intval($record['level'])));
            $field->addField('message', $record['message']);
            $field->addField('formatted', $record['formatted']);
            $field->addField('datetime', date('Y-m-d H:i:s', $record['datetime']->format('U')));
            if (defined('RBID')) {
                $blogName = ACMS_RAM::blogName(RBID) ?? '';
                $field->addField('rootBlogName', trim($blogName));
            } elseif (defined('DOMAIN')) {
                $field->addField('rootBlogName', DOMAIN);
            }
            $field->addField('url', REQUEST_URL);
            $field->addField('remoteAddr', REMOTE_ADDR);
            $field->addField('auditLogUrl', BASE_URL . 'bid/1/admin/audit_log/');

            $subjectTpl = findTemplate('mail/logger/subject.txt');
            $bodyTpl = findTemplate('mail/logger/body.txt');
            if (empty($subjectTpl) || empty($bodyTpl)) {
                return;
            }
            $subject = $this->buildMailTxt($subjectTpl, $field);
            $body = $this->buildMailTxt($bodyTpl, $field);
            $this->nofify($this->getMailTo(), $this->getMailFrom(), $this->getMailBcc(), $subject, $body);
        } catch (\Exception $e) {
        }
    }

    /**
     * メール通知する
     *
     * @param string $to
     * @param string $from
     * @param string $bcc
     * @param string $subject
     * @param string $body
     * @return void
     * @throws RuntimeException
     */
    protected function nofify(string $to, string $from, string $bcc, string $subject, string $body): void
    {
        if (empty($to) || empty($from)) {
            return;
        }
        if (empty($subject) || empty($body)) {
            return;
        }
        $mailer = Mailer::init();
        $mailer = $mailer->setFrom($from)
            ->setTo($to)
            ->setBcc($bcc)
            ->setSubject($subject)
            ->setBody($body);
        $mailer->send();
    }

    /**
     * 通知を一時的に止めるか判断
     *
     * @param string $lockFilePath
     * @param string $countFilePath
     * @param int $limit
     * @param int $timeRange
     * @param int $stopTime
     * @return bool
     */
    protected function suppressNotifications(string $lockFilePath, string $countFilePath, int $limit, int $timeRange, int $stopTime): bool
    {
        try {
            $suppress = false;
            $amount = 1;
            if (LocalStorage::exists($lockFilePath)) {
                $lastModified = LocalStorage::lastModified($lockFilePath);
                if (time() < ($lastModified + ($stopTime * 60))) {
                    // Stop notification
                    return false;
                } else {
                    // Restart notification
                    LocalStorage::remove($lockFilePath);
                }
            }
            if (LocalStorage::exists($countFilePath)) {
                $lastModified = LocalStorage::lastModified($countFilePath);
                if (time() < ($lastModified + ($timeRange * 60))) {
                    // Count up
                    $num = LocalStorage::get($countFilePath);
                    if ($amount = intval($num) + 1) {
                        LocalStorage::put($countFilePath, (string) $amount);
                    }
                } else {
                    // Reset count
                    LocalStorage::put($countFilePath, '1');
                }
            } else {
                // Start count
                LocalStorage::put($countFilePath, '1');
            }
            if ($amount > $limit) {
                // Create flag to stop notification
                $suppress = true;
                LocalStorage::put($lockFilePath, 'stop notification');
            }
            if ($suppress) {
                // Notify that notifications are suspended

                $field = new Field();
                $field->addField('datetime', date('Y-m-d H:i:s', time()));
                if (defined('RBID')) {
                    $blogName = ACMS_RAM::blogName(RBID) ?? '';
                    $field->addField('rootBlogName', trim($blogName));
                } elseif (defined('DOMAIN')) {
                    $field->addField('rootBlogName', DOMAIN);
                }
                $field->addField('auditLogUrl', BASE_URL . 'bid/1/admin/audit_log/');
                $field->addField('stop_time', $stopTime);
                $field->addField('restart_datetime', date('Y-m-d H:i:s', time() + ($stopTime * 60)));

                $subjectTpl = findTemplate('mail/logger/suspend-subject.txt');
                $bodyTpl = findTemplate('mail/logger/suspend-body.txt');
                if (!empty($subjectTpl) && !empty($bodyTpl)) {
                    $subject = $this->buildMailTxt($subjectTpl, $field);
                    $body = $this->buildMailTxt($bodyTpl, $field);
                    $this->nofify($this->getMailTo(), $this->getMailFrom(), $this->getMailBcc(), $subject, $body);
                }
                return false;
            }
        } catch (\Exception $e) {
        }
        return true;
    }

    protected function buildMailTxt($tplPath, $field)
    {
        $tplTxt = LocalStorage::get($tplPath);
        $tpl = (new \Acms\Services\View\Engine())->init($tplTxt, new ACMS_Corrector());
        $vars = Tpl::buildField($field, $tpl);
        $tpl->add(null, $vars);

        return buildIF($tpl->get());
    }

    /**
     * エラー通知する宛先のアドレスを取得
     *
     * @return bool|string
     */
    protected function getMailTo()
    {
        if ($email = env('ALERT_EMAIL_TO', false)) {
            return $email;
        }
        $userService = Application::make('user');
        $admin = $userService->getAdminUserWithMinId();

        return $admin['user_mail'] ?? false;
    }

    /**
     * エラー通知メールのFromを取得
     *
     * @return string
     */
    protected function getMailFrom(): string
    {
        return env('ALERT_EMAIL_FROM', 'info@example.com');
    }

    /**
     * エラー通知メールのBccを取得
     *
     * @return string
     */
    protected function getMailBcc(): string
    {
        return env('ALERT_EMAIL_BCC', '');
    }
}
