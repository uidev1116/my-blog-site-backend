import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import { NodeSelection } from '@tiptap/pm/state';
import { useCallback, useRef, useState, useEffect } from 'react';
import { v4 as uuid } from 'uuid';

import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { MenuProps } from '@features/block-editor/components/menus/types';
import MediaInsert from '@features/media/components/media-insert/media-insert';
import MediaUpdate from '@features/media/components/media-update/media-update';
import { ImageBlockWidth } from '@features/block-editor/extensions/ImageBlock/components/ImageBlockWidth';
import { ImageBlockCaption } from '@features/block-editor/extensions/ImageBlock/components/ImageBlockCaption';
import { ImageBlockLink } from '@features/block-editor/extensions/ImageBlock/components/ImageBlockLink';
import { MediaItem } from '@features/media/types';
import { useMediaSelect } from '@hooks/use-media';
import { useMediaUpdate } from '@features/block-editor/extensions/ImageBlock/mediaUpdateHooks';
import { AlignPicker } from '@features/block-editor/components/panels';

type TippyInstance = Parameters<
  NonNullable<NonNullable<React.ComponentPropsWithoutRef<typeof BaseBubbleMenu>['tippyOptions']>['onCreate']>
>[0];

export const ImageBlockMenu = ({ editor, appendTo }: MenuProps): JSX.Element => {
  const menuRef = useRef<HTMLDivElement>(null);
  const tippyInstance = useRef<TippyInstance | null>(null);
  const [hasMainImageField, setHasMainImageField] = useState(false);
  const [isMainImageActive, setIsMainImageActive] = useState(false);
  const inspectMedia = useCallback(() => {
    const { selection } = editor.state;
    if (selection instanceof NodeSelection) {
      const selectedNode = selection.node; // 選択されているノードを取得
      if (selectedNode.type.name === 'imageBlock') {
        return selectedNode;
      }
    }
  }, [editor]);
  const { isInsertModalOpen, tab, files, handleModalClose, handleSelectClick, uploadFile } = useMediaSelect();
  const { targetMediaId, selfImgSrc, isUpdateModalOpen, handleUpdateModalClose, handleUpdateClick } = useMediaUpdate({
    inspectMedia,
  });

  const handleInsertMedia = useCallback(
    (items: MediaItem[]) => {
      const [item] = items;

      if (item.media_thumbnail) {
        editor.chain().focus(undefined, { scrollIntoView: false }).updateMediaImageBlock(item).run();
      }
      handleModalClose();
    },
    [editor, handleModalClose]
  );

  const handleUpdateMedia = useCallback(
    (item: MediaItem) => {
      if (item.media_thumbnail) {
        editor.chain().focus(undefined, { scrollIntoView: false }).updateMediaImageBlock(item).run();
      }
      handleUpdateModalClose();
    },
    [editor, handleUpdateModalClose]
  );

  const shouldShow = useCallback(() => {
    const isActive = editor.isActive('imageBlock');
    return isActive;
  }, [editor]);

  const onWidthChange = useCallback(
    (value: string) => {
      editor.chain().focus(undefined, { scrollIntoView: false }).setImageBlockWidth(value).run();
    },
    [editor]
  );

  /**
   * 画像URLから画像をメディアにアップロードする
   * 自前ホストの画像のみ
   */
  const handleUploadClick = useCallback(async () => {
    const res = await fetch(selfImgSrc);
    const blob = await res.blob();
    const filename = selfImgSrc.split('/').pop() || '';
    const ext = blob.type.split('/')[1] || 'jpg';
    const name = filename.includes('.') ? filename : `${filename}.${ext}`;
    const file = new File([blob], name, { type: blob.type });

    uploadFile([file]);
  }, [uploadFile, selfImgSrc]);

  /**
   * メイン画像に設定する
   */
  const handleSetMainImage = useCallback(() => {
    const mainImageValues = document.querySelectorAll(
      ACMS.Config.blockEditorConfig.setMainImageMark || '.js-block-editor-set-main-image'
    ) as NodeListOf<HTMLInputElement>;
    if (mainImageValues && mainImageValues.length > 0) {
      [].forEach.call(mainImageValues, (mainImageValue: HTMLInputElement) => {
        setIsMainImageActive(true);
        mainImageValue.dispatchEvent(new Event('change', { bubbles: true }));
        mainImageValue?.dispatchEvent(
          new CustomEvent('acms.set-main-image', {
            detail: {
              media_id: editor.getAttributes('imageBlock').mediaId,
              media_thumbnail: editor.getAttributes('imageBlock').src,
              media_type: 'image',
            },
          })
        );
      });
    }
  }, [editor]);

  /**
   * メイン画像に設定されているかどうか
   */
  useEffect(() => {
    const mainImageValues = document.querySelectorAll(
      ACMS.Config.blockEditorConfig.setMainImageMark || '.js-block-editor-set-main-image'
    ) as NodeListOf<HTMLInputElement>;
    if (mainImageValues && mainImageValues.length > 0) {
      const mainImageMids = Array.from(mainImageValues).map((mainImageValue: HTMLInputElement) => mainImageValue.value);
      setHasMainImageField(true);
      setIsMainImageActive(mainImageMids.includes(String(editor.getAttributes('imageBlock').mediaId)));
    }
  }, [editor, editor.state]);

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey={`imageBlockMenu-${uuid()}`}
      shouldShow={shouldShow}
      updateDelay={0}
      tippyOptions={{
        offset: [0, 8],
        onCreate: (instance) => {
          tippyInstance.current = instance;
        },
        appendTo: () => appendTo?.current ?? document.body,
      }}
    >
      <Toolbar shouldShowContent={shouldShow()} ref={menuRef}>
        <Toolbar.Button type="button" tooltip="メディア選択" onClick={handleSelectClick} aria-label="メディアを選択">
          <Icon name="perm_media" />
        </Toolbar.Button>
        {targetMediaId && (
          <Toolbar.Button
            type="button"
            tooltip="メディアを編集"
            onClick={handleUpdateClick}
            aria-label="メディアを編集"
          >
            <Icon name="edit" />
          </Toolbar.Button>
        )}
        {!targetMediaId && selfImgSrc && (
          <Toolbar.Button
            type="button"
            tooltip="アップロード"
            onClick={handleUploadClick}
            aria-label="画像をアップロード"
          >
            <Icon name="upload" />
          </Toolbar.Button>
        )}
        <Toolbar.Divider />
        <ImageBlockLink
          initialUrl={editor.getAttributes('imageBlock').link}
          initialOpenInNewTab={editor.getAttributes('imageBlock').target === '_blank'}
          onChange={(link, openInNewTab) => {
            editor.chain().focus(undefined, { scrollIntoView: false }).setImageBlockLink(link, openInNewTab).run();
          }}
        />
        <ImageBlockCaption
          caption={editor.getAttributes('imageBlock').caption}
          alt={editor.getAttributes('imageBlock').alt}
          onChange={(caption, alt) => {
            editor
              .chain()
              .focus(undefined, { scrollIntoView: false })
              .setImageBlockCaption(caption)
              .setImageBlockAlt(alt)
              .run();
          }}
        />
        <AlignPicker
          align={editor.getAttributes('imageBlock').align}
          onChange={(align) => {
            editor.chain().focus(undefined, { scrollIntoView: false }).setImageBlockAlign(align).run();
          }}
        />
        <Toolbar.Button
          type="button"
          tooltip="拡大表示しない"
          aria-label="画像を拡大表示しない"
          onClick={() => {
            editor.chain().focus(undefined, { scrollIntoView: false }).toggleImageBlockLightbox().run();
          }}
          active={editor.getAttributes('imageBlock').noLightbox === 'true'}
        >
          <Icon name="search_off" />
        </Toolbar.Button>
        {hasMainImageField && targetMediaId && (
          <Toolbar.Button
            type="button"
            tooltip="メイン画像に設定"
            onClick={handleSetMainImage}
            active={isMainImageActive}
            aria-label="メイン画像に設定"
          >
            <Icon name="imagesmode" />
          </Toolbar.Button>
        )}
        <Toolbar.Divider />
        <ImageBlockWidth onChange={onWidthChange} value={editor.getAttributes('imageBlock').displayWidth} />
      </Toolbar>
      <MediaInsert
        isOpen={isInsertModalOpen}
        onInsert={handleInsertMedia}
        onClose={handleModalClose}
        tab={tab}
        files={files}
        filetype="image"
      />
      <MediaUpdate
        isOpen={isUpdateModalOpen}
        mid={targetMediaId || ''}
        onClose={handleUpdateModalClose}
        onUpdate={handleUpdateMedia}
      />
    </BaseBubbleMenu>
  );
};

export default ImageBlockMenu;
