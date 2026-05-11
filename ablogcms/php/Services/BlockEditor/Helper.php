<?php

namespace Acms\Services\BlockEditor;

use DOMDocument;
use DOMXPath;
use DOMElement;
use DOMText;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Common;
use Acms\Services\Common\CorrectorFactory;

class Helper
{
    /**
     * 画像リサイズの幅
     *
     * @var int
     */
    protected $resizeImageSize;

    /**
     * Lightboxクラス名
     *
     * @var string
     */
    protected $lightboxClass;

    public function __construct(?int $resizeImageSize = null)
    {
        if ($resizeImageSize === null) {
            $this->resizeImageSize = (int) config('block_editor_resize_image_size', 1000);
        } else {
            $this->resizeImageSize = $resizeImageSize;
        }
        $this->lightboxClass = config('block_editor_lightbox_class', 'js-smartphoto');
    }

    /**
     * ブロックエディターのHTMLを修正する
     *
     * @param string $html
     * @param boolean $resizeImage
     * @return string
     */
    public function fix(string $html, bool $resizeImage = true): string
    {
        if (!$html) {
            return '';
        }
        $doc = $this->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $mediaList = $this->loadMedia($xpath);
        if ($mediaList) {
            $this->fixMediaImages($xpath, $mediaList, $resizeImage); // メディア画像の修正
            $this->fixMediaFiles($xpath, $mediaList); // メディアファイルの修正
        }
        $this->removeLastEmptyParagraph($xpath); // 最後の空の段落を削除

        // 最終HTML生成
        $innerHTML = $this->getInnerHTML($doc);
        $innerHTML = Common::replaceDeliveryUrlAll($innerHTML);

        return $innerHTML;
    }

    /**
     * メディアIDを抽出する
     *
     * @param string $html
     * @return int[]
     */
    public function extractMediaId(string $html): array
    {
        $mediaIds = [];
        if (!$html) {
            return $mediaIds;
        }
        $doc = $this->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $mediaIdDom = $xpath->query('//*[@data-mid]');
        if ($mediaIdDom && $mediaIdDom->length > 0) {
            foreach ($mediaIdDom as $dom) {
                if (!$dom instanceof DOMElement) {
                    continue;
                }
                $mid = (int) $dom->getAttribute('data-mid');
                if ($mid && !in_array($mid, $mediaIds, true)) {
                    $mediaIds[] = $mid;
                }
            }
        }

        return $mediaIds;
    }

    /**
     * メディアIDを修正する
     *
     * @param string $html
     * @param array<int, int> $mediaIdMap
     * @return string
     */
    public function fixMediaId(string $html, array $mediaIdMap): string
    {
        if (!$html || !$mediaIdMap) {
            return $html;
        }
        $doc = $this->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $mediaIdDom = $xpath->query('//*[@data-mid]');
        if ($mediaIdDom && $mediaIdDom->length > 0) {
            foreach ($mediaIdDom as $dom) {
                if (!$dom instanceof DOMElement) {
                    continue;
                }
                $mid = (int) $dom->getAttribute('data-mid');
                if (isset($mediaIdMap[$mid])) {
                    $dom->setAttribute('data-mid', (string) $mediaIdMap[$mid]);
                }
            }
        }
        return $this->getInnerHTML($doc);
    }

    /**
     * HTMLをDOMDocumentとしてロードする
     *
     * @param string $html
     * @return DOMDocument
     */
    private function loadHTML(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        // UTF-8を明示的に指定するためにXML宣言を追加
        // 参考: https://akamist.com/blog/archives/4649
        $wrapped = '<?xml encoding="utf-8" ?><body>' . $html . '</body>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $doc;
    }

    /**
     * DOMDocumentからHTML文字列を取得する
     *
     * @param DOMDocument $doc
     * @return string
     */
    private function getInnerHTML(DOMDocument $doc): string
    {
        $innerHTML = '';
        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $node) {
                $innerHTML .= $doc->saveHTML($node);
            }
        }
        return $innerHTML;
    }

    /**
     * htmlに存在するメディア情報を取得する
     *
     * @param DOMXPath $xpath
     * @return array{
     *  path: string,
     *  width: string,
     *  height: string,
     *  permalink: string,
     *  icon: string,
     *  iconWidth: string,
     *  iconHeight: string,
     *  extension: string,
     *  fileSize: int,
     *  imagePermalink: string,
     * }[]
     */
    private function loadMedia(DOMXPath $xpath): array
    {
        $mediaList = [];
        if ($mediaIdDom = $xpath->query('//*[@data-mid]')) {
            $mediaIds = array_map(function ($dom) {
                return ($dom instanceof DOMElement) ? (int) $dom->getAttribute('data-mid') : null;
            }, iterator_to_array($mediaIdDom));
            $mediaIds = array_filter($mediaIds, function ($id) {
                return $id !== null;
            });
            $mediaList = Media::getMediaList(array_values($mediaIds));
        }
        return $mediaList;
    }

    /**
     * メディア画像の修正
     *
     * @param DOMXPath $xpath
     * @param array{
     *  path: string,
     *  width: string,
     *  height: string,
     *  permalink: string,
     *  icon: string,
     *  iconWidth: string,
     *  iconHeight: string,
     *  extension: string,
     *  fileSize: int,
     *  imagePermalink: string,
     * }[] $mediaList
     * @param boolean $resize
     * @return void
     */
    private function fixMediaImages(DOMXPath $xpath, array $mediaList, bool $resize = true): void
    {
        $imgBlocks = $xpath->query('//div[@data-type="imageBlock"]');
        if (!$imgBlocks || $imgBlocks->length === 0) {
            return;
        }
        foreach ($imgBlocks as $block) {
            if (!$block instanceof DOMElement) {
                continue;
            }
            $imgTagList = $xpath->query('.//*[@data-mid]', $block);
            if (!$imgTagList || $imgTagList->length === 0) {
                continue;
            }
            $imgTag = $imgTagList->item(0);
            $mid = ($imgTag instanceof DOMElement) ? (int) $imgTag->getAttribute('data-mid') : null;
            if (!$mid || !isset($mediaList[$mid])) {
                continue;
            }
            $media = $mediaList[$mid];
            if ($imgTag instanceof DOMElement) {
                $path = $media['path'];
                if ($resize) {
                    $factory = CorrectorFactory::singleton();
                    $path = $factory->call('resizeImg', $media['path'], [$this->resizeImageSize]); // 画像リサイズ
                }
                $imgTag->setAttribute('src', Common::convertRelativeUrlToAbsolute($path ? $path : '', BASE_URL));
                $imgTag->setAttribute('width', $media['width']);
                $imgTag->setAttribute('height', $media['height']);
                if (!$block->getAttribute('data-link')) {
                    $linkTags = $xpath->query('ancestor::a', $imgTag);
                    if ($linkTags && $linkTags->length > 0) {
                        $linkTag = $linkTags->item(0);
                        if ($linkTag instanceof DOMElement) {
                            $linkTag->setAttribute('href', Common::convertRelativeUrlToAbsolute($media['imagePermalink'], BASE_URL));
                            if ($linkTag->getAttribute('data-no-lightbox') !== 'false') {
                                $linkTag->setAttribute('class', $this->lightboxClass);
                                $linkTag->setAttribute('data-group', $block->getAttribute('data-eid'));
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * メディアファイルの修正
     *
     * @param DOMXPath $xpath
     * @param array{
     *  path: string,
     *  width: string,
     *  height: string,
     *  permalink: string,
     *  icon: string,
     *  iconWidth: string,
     *  iconHeight: string,
     *  extension: string,
     *  fileSize: int,
     *  imagePermalink: string,
     * }[] $mediaList
     * @return void
     */
    private function fixMediaFiles(DOMXPath $xpath, array $mediaList): void
    {
        $fileBlocks = $xpath->query('//div[@data-type="fileBlock"]');
        if (!$fileBlocks || $fileBlocks->length === 0) {
            return;
        }
        foreach ($fileBlocks as $block) {
            $mid = ($block instanceof DOMElement) ? (int) $block->getAttribute('data-mid') : null;
            if (!$mid || !isset($mediaList[$mid])) {
                continue;
            }
            $media = $mediaList[$mid];
            $iconAbsoluteUrl = Common::convertRelativeUrlToAbsolute($media['icon'], BASE_URL);
            if ($block instanceof DOMElement) {
                $block->setAttribute('data-icon', $iconAbsoluteUrl);
                $block->setAttribute('data-icon-width', $media['iconWidth']);
                $block->setAttribute('data-icon-height', $media['iconHeight']);
                $block->setAttribute('data-extension', $media['extension']);
                $block->setAttribute('data-file-size', (string) $media['fileSize']);
            }
            $linkTags = $xpath->query('.//a', $block);
            $iconTags = $xpath->query('.//img', $block);
            if ($linkTags && $linkTags->length > 0) {
                $linkTag = $linkTags->item(0);
                if ($linkTag instanceof DOMElement) {
                    $linkTag->setAttribute('href', Common::convertRelativeUrlToAbsolute($media['permalink'], BASE_URL));
                }
            }
            if ($iconTags && $iconTags->length > 0) {
                $iconTag = $iconTags->item(0);
                if ($iconTag instanceof DOMElement) {
                    $iconTag->setAttribute('src', $iconAbsoluteUrl);
                    $iconTag->setAttribute('width', $media['iconWidth']);
                    $iconTag->setAttribute('height', $media['iconHeight']);
                }
            }
        }
    }

    /**
     * 最後の空の段落を削除する
     *
     * @param DOMXPath $xpath
     * @return void
     */
    private function removeLastEmptyParagraph(DOMXPath $xpath): void
    {
        $paragraphs = $xpath->query('//p');
        if ($paragraphs && $paragraphs->length > 0) {
            /** @var DOMElement $lastP */
            $lastP = $paragraphs->item($paragraphs->length - 1);
            // 中身が空 or <br> などしかないか確認
            $isEmpty = true;
            foreach ($lastP->childNodes as $child) {
                if ($child instanceof DOMText) {
                    // &nbsp; は UTF-8 で \xC2\xA0 として扱われる
                    $text = trim($child->nodeValue ?? '');
                    if ($text !== '' && preg_replace('/[\xC2\xA0　]/u', '', $text) !== '') {
                        $isEmpty = false;
                        break;
                    }
                } elseif ($child instanceof DOMElement) {
                    if (strtolower($child->nodeName) === 'br') {
                        continue;
                    }
                    // 画像やスパンなど他の要素が入っていれば空ではない
                    $isEmpty = false;
                    break;
                }
            }
            // 空の段落を削除
            if ($isEmpty && $lastP->parentNode) {
                $lastP->parentNode->removeChild($lastP);
            }
        }
    }
}
