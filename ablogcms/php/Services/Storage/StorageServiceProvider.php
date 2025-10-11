<?php

namespace Acms\Services\Storage;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Logger;
use AsyncAws\S3\S3Client;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * register service
     *
     * @param \Acms\Services\Container $container
     *
     * @return void
     */
    public function register(Container $container)
    {
        $container->singleton('storage', function () {
            $filesystem = new Filesystem();
            if (defined('CHMOD_DIR')) {
                $filesystem->setDirectoryMod(CHMOD_DIR);
            }
            if (defined('CHMOD_FILE')) {
                $filesystem->setFileMod(CHMOD_FILE);
            }
            return $filesystem;
        });

        $s3Client = env('STORAGE_DRIVER', 'local') === 's3' ? new S3Client([
            'accessKeyId' => env('STORAGE_S3_KEY'),
            'accessKeySecret' => env('STORAGE_S3_SECRET'),
            'region' => env('STORAGE_S3_REGION', 'ap-northeast-1'),
        ]) : null;

        $container->singleton('public-storage', function () use ($s3Client) {
            if ($s3Client) {
                if (editionWithProfessional()) {
                    $bucket = env('STORAGE_S3_PUBLIC_BUCKET', '');
                    $prefix = env('STORAGE_S3_PUBLIC_PREFIX', '');
                    return new S3($s3Client, $bucket, $prefix);
                } else {
                    Logger::error('AWS S3 との連携機能は、プロフェッショナル版以上でのみ利用可能です。');
                }
            }
            return Application::make('storage');
        });

        $container->singleton('private-storage', function () use ($s3Client) {
            if ($s3Client) {
                if (editionWithProfessional()) {
                    $bucket = env('STORAGE_S3_PRIVATE_BUCKET', '');
                    $prefix = env('STORAGE_S3_PRIVATE_PREFIX', '');
                    return new S3($s3Client, $bucket, $prefix);
                }
            }
            return Application::make('storage');
        });
    }
}
