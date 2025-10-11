<?php

use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\PublicStorage;

class ImageResize
{
    /**
     * @var int
     */
    public const MIME_GIF = 1;

    /**
     * @var int
     */
    public const MIME_PNG = 2;

    /**
     * @var int
     */
    public const MIME_BMP = 3;

    /**
     * @var int
     */
    public const MIME_XBM = 4;

    /**
     * @var int
     */
    public const MIME_JPEG = 5;

    /**
     * @var int
     */
    public const SCALE_TO_FILL = 1;        // 出力サイズにめいっぱい広げる

    /**
     * @var int
     */
    public const SCALE_ASPECT_FIT = 2;     // aspect比を維持して、ちょうど入るようにする

    /**
     * @var int
     */
    public const SCALE_ASPECT_FILL = 3;    // aspect比を維持して、めいっぱい広げる

    /**
     * @var string
     */
    protected $srcPath;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var int
     */
    protected $qualityJpeg = 95;

    /**
     * @var int
     */
    protected $originalW;

    /**
     * @var int
     */
    protected $originalH;

    /**
     * @var int
     */
    protected $srcX = 0;

    /**
     * @var int
     */
    protected $srcY = 0;

    /**
     * @var int
     */
    protected $srcW;

    /**
     * @var int
     */
    protected $srcH;

    /**
     * @var int
     */
    protected $destX = 0;

    /**
     * @var int
     */
    protected $destY = 0;

    /**
     * @var int
     */
    protected $destW;

    /**
     * @var int
     */
    protected $destH;

    /**
     * @var int
     */
    protected $canvasW;

    /**
     * @var int
     */
    protected $canvasH;

    /**
     * @var int
     */
    protected $colorR = 0;

    /**
     * @var int
     */
    protected $colorG = 0;

    /**
     * @var int
     */
    protected $colorB = 0;

    /**
     * @var int
     */
    protected $mode = self::SCALE_ASPECT_FILL;

    /**
     * @var \Acms\Services\Image\Contracts\ImageEngine
     */
    private $engine;

    /**
     * Constructor
     *
     * @param string $path
     * @throws \Exception
     */
    public function __construct(string $path)
    {
        if (!PublicStorage::getImageSize($path)) {
            AcmsLogger::warning('画像が読み込めないため、リサイズできませんでした', [
                'path' => $path,
            ]);
            throw new Exception('Can\'t read image file');
        }
        $this->srcPath = $path;
        $this->engine = Application::make('image.engine');
        [$this->originalW, $this->originalH] = $this->engine->getSize($path);
    }

    /**
     * リサイズモードをセット
     *
     * @param int $mode
     * @return void
     */
    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * 画像クオリティをセット
     *
     * @param int $quality
     * @return void
     */
    public function setQuality(int $quality): void
    {
        $this->qualityJpeg = $quality;
    }

    /**
     * 背景色をセット
     *
     * @param string $color
     * @throws \Exception
     * @return void
     */
    public function setBgColor(string $color): void
    {
        $color = ltrim($color, '#');

        if (preg_match('/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $color, $matches)) {
            $this->colorR = hexdec($matches[1]);
            $this->colorG = hexdec($matches[2]);
            $this->colorB = hexdec($matches[3]);
        }
        if (
            0
            || $this->colorR > 255 || $this->colorR < 0
            || $this->colorG > 255 || $this->colorG < 0
            || $this->colorB > 255 || $this->colorB < 0
        ) {
            AcmsLogger::warning('画像リサイズで、無効な色指定されました', [
                'colorR' => $this->colorR,
                'colorG' => $this->colorG,
                'colorB' => $this->colorB,
            ]);
            throw new Exception('Incorrect Color Value');
        }
    }

    /**
     * リサイズした画像を保存
     *
     * @param string $destPath
     * @return void
     */
    public function save(string $destPath): void
    {
        $color = [$this->colorR, $this->colorG, $this->colorB];
        $this->engine->setImageQuality((int) config('resize_image_jpeg_quality', 75));
        $this->engine->resizeImage($this->srcPath, $destPath, $this->srcW, $this->srcH, $this->srcX, $this->srcY, $this->destW, $this->destH, $this->destX, $this->destY, $this->canvasW, $this->canvasH, $color);
        $this->engine->copyImageAsWebp($destPath, "{$destPath}.webp");
    }

    /**
     * リサイズ（幅・高さを指定）
     *
     * @param int $width
     * @param int $height
     * @return void
     */
    public function resize(int $width, int $height): void
    {
        $this->canvasW = $width;
        $this->canvasH = $height;

        switch ($this->mode) {
            case self::SCALE_TO_FILL:
                $this->srcW = $this->originalW;
                $this->srcH = $this->originalH;
                $this->destW = $width;
                $this->destH = $height;
                break;
            case self::SCALE_ASPECT_FIT:
                $this->resizeToAspectFit();
                break;
            case self::SCALE_ASPECT_FILL:
                $this->resieToAspectFill();
                break;
        }
    }

    /**
     * リサイズ（高さを指定・幅は自動計算）
     *
     * @param int $height
     * @return void
     */
    public function resizeToHeight(int $height): void
    {
        $this->canvasH = $height;
        $this->srcW = $this->originalW;
        $this->srcH = $this->originalH;
        $ratio = $height / $this->originalH;

        if ($height < $this->originalH) {
            $this->destH = $height;
            $this->destW = (int) ceil($this->originalW * $ratio);
            $this->canvasW = $this->destW;
        } else {
            $this->destW = $this->originalW;
            $this->destH = $this->originalH;
            $this->canvasW = $this->originalW;
            $this->destY = (int) ceil(($height - $this->originalH) / 2);
        }
    }

    /**
     * リサイズ（幅を指定・高さは自動計算）
     *
     * @param int $width
     * @return void
     */
    public function resizeToWidth(int $width): void
    {
        $this->canvasW = $width;
        $this->srcW = $this->originalW;
        $this->srcH = $this->originalH;
        $ratio = $width / $this->originalW;

        if ($width < $this->originalW) {
            $this->destW = $width;
            $this->destH = (int) ceil($this->originalH * $ratio);
            $this->canvasH = $this->destH;
        } else {
            $this->destW = $this->originalW;
            $this->destH = $this->originalH;
            $this->canvasH = $this->originalH;
            $this->destX = (int) ceil(($width - $this->originalW) / 2);
        }
    }

    /**
     * アクスペクト比を維持するようにサイズを計算
     *
     * @return void
     */
    private function resizeToAspectFit(): void
    {
        $this->srcW = $this->originalW;
        $this->srcH = $this->originalH;

        $srcRatio = $this->originalW / $this->originalH;
        $destRatio = $this->canvasW / $this->canvasH;

        if ($srcRatio > $destRatio) {
            // 横幅いっぱい
            $this->destW = $this->canvasW;
            $this->destH = (int) ceil($this->destW / $srcRatio);
            $this->destY = (int) ceil(($this->canvasH - $this->destH) / 2);
        } else {
            // 縦幅いっぱい
            $this->destH = $this->canvasH;
            $this->destW = (int) ceil($this->destH * $srcRatio);
            $this->destX = (int) ceil(($this->canvasW - $this->destW) / 2);
        }
    }

    /**
     * 指定されたサイズを埋めるようにトリミングしたサイズを取得
     *
     * @return void
     */
    private function resieToAspectFill(): void
    {
        $this->srcW = $this->originalW;
        $this->srcH = $this->originalH;

        $this->destW = $this->canvasW;
        $this->destH = $this->canvasH;

        $srcRatio = $this->originalW / $this->originalH;
        $destRatio = $this->canvasW / $this->canvasH;


        if ($srcRatio > $destRatio) {
            // 左右をトリミング
            $this->srcH = $this->originalH;
            $this->srcW = (int) ceil($this->srcH * $destRatio);
            $this->srcX = (int) ceil(($this->originalW - $this->srcW) / 2);
        } else {
            // 上下をトリミング
            $this->srcH = (int) ceil($this->srcW / $destRatio);
            $this->srcW = $this->originalW;
            $this->srcY = (int) ceil(($this->originalH - $this->srcH) / 2);
        }
    }
}
