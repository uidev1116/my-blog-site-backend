<?php

class ACMS_POST_Logger_Download extends ACMS_POST_Logger_Info
{
    public function post()
    {
        try {
            $this->validateDownload();

            $limits = configArray('admin_limit_option');
            $limit = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
            $limit = intval($limit);
            $levels = $this->Get->getArray('level');
            $suid = $this->Get->get('suid', 0);
            $repository = App::make('acms-logger-repository');

            list($sql, $count) = $repository->getIndexSql($limit, PAGE, $levels, $suid, START, END);
            $q = $sql->get(dsn());
            $statement = DB::query($q, 'exec');

            $tempFile = 'audit_log_' . date('Y-m-d', REQUEST_TIME) . '.json';
            if (START && strpos(START, '1000-01-01') === false) {
                $tempFile = 'audit_log_' . date('Y-m-d', strtotime(START)) . '.json';
            }
            $path = MEDIA_STORAGE_DIR . $tempFile;
            $first = true;
            $fp = fopen($path, 'w');
            if ($fp === false) {
                throw new \RuntimeException('ファイルを開くことができませんでした: ' . $path);
            }
            fwrite($fp, "[\n");

            while ($log = DB::next($statement)) {
                if ($first) {
                    $first = false;
                } else {
                    fwrite($fp, ",\n");
                }
                $json = json_encode($this->buildData($log), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    throw new \RuntimeException('JSONエンコードに失敗しました: ' . json_last_error_msg());
                }
                fwrite($fp, $json);
            }
            fwrite($fp, "\n]");
            fclose($fp);

            Common::download($path, $tempFile, false, true);
        } catch (\Exception $e) {
            AcmsLogger::error($e->getMessage());
            return $this->Post;
        }
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    protected function validateDownload(): void
    {
        if (!sessionWithAdministration()) {
            throw new \RuntimeException('アクセス権限がありません');
        }
    }
}
