import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import { NodeSelection } from '@tiptap/pm/state';
import { useCallback, useRef } from 'react';
import { v4 as uuid } from 'uuid';

import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { MenuProps } from '@features/block-editor/components/menus/types';
import MediaInsert from '@features/media/components/media-insert/media-insert';
import MediaUpdate from '@features/media/components/media-update/media-update';
import { FileBlockCaption } from '@features/block-editor/extensions/FileBlock/components/FileBlockCaption';
import { MediaItem } from '@features/media/types';
import { useMediaSelect } from '@features/block-editor/extensions/MediaUpload/view/hooks';
import { useMediaUpdate } from '@features/block-editor/extensions/ImageBlock/mediaUpdateHooks';
import { AlignPicker } from '@features/block-editor/components/panels';

type TippyInstance = Parameters<
  NonNullable<NonNullable<React.ComponentPropsWithoutRef<typeof BaseBubbleMenu>['tippyOptions']>['onCreate']>
>[0];

export const FileBlockMenu = ({ editor, appendTo }: MenuProps): JSX.Element => {
  const menuRef = useRef<HTMLDivElement>(null);
  const tippyInstance = useRef<TippyInstance | null>(null);
  const inspectMedia = useCallback(() => {
    const { selection } = editor.state;
    if (selection instanceof NodeSelection) {
      const selectedNode = selection.node; // 選択されているノードを取得
      if (selectedNode.type.name === 'fileBlock') {
        return selectedNode;
      }
    }
  }, [editor]);
  const { isInsertModalOpen, tab, files, handleModalClose, handleSelectClick } = useMediaSelect();
  const { targetMediaId, isUpdateModalOpen, handleUpdateModalClose, handleUpdateClick } = useMediaUpdate({
    inspectMedia,
  });

  const handleInsertMedia = useCallback(
    (items: MediaItem[]) => {
      const [item] = items;

      if (item.media_permalink) {
        editor.chain().focus(undefined, { scrollIntoView: false }).updateMediaFileBlock(item).run();
      }
      handleModalClose();
    },
    [editor, handleModalClose]
  );

  const handleUpdateMedia = useCallback(
    (item: MediaItem) => {
      if (item.media_permalink) {
        editor.chain().focus(undefined, { scrollIntoView: false }).updateMediaFileBlock(item).run();
      }
      handleUpdateModalClose();
    },
    [editor, handleUpdateModalClose]
  );

  const shouldShow = useCallback(() => {
    const isActive = editor.isActive('fileBlock');
    return isActive;
  }, [editor]);

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey={`fileBlockMenu-${uuid()}`}
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
        <Toolbar.Divider />
        <FileBlockCaption
          caption={editor.getAttributes('fileBlock').caption ?? ''}
          alt={editor.getAttributes('fileBlock').alt ?? ''}
          onChange={(caption: string, alt: string) => {
            editor
              .chain()
              .focus(undefined, { scrollIntoView: false })
              .setFileBlockCaption(caption)
              .setFileBlockAlt(alt)
              .run();
          }}
        />
        <AlignPicker
          align={editor.getAttributes('fileBlock').align}
          onChange={(align) => {
            editor.chain().focus(undefined, { scrollIntoView: false }).setFileBlockAlign(align).run();
          }}
        />
        <Toolbar.Button
          type="button"
          tooltip="ボタンで表示"
          aria-label="ボタンで表示"
          onClick={() => {
            const newDisplayType = editor.getAttributes('fileBlock').displayType === 'button' ? 'icon' : 'button';
            editor.chain().focus(undefined, { scrollIntoView: false }).setFileBlockDisplayType(newDisplayType).run();
          }}
          disabled={!editor.getAttributes('fileBlock').caption}
          active={editor.getAttributes('fileBlock').displayType === 'button'}
        >
          <Icon name="variable_insert" />
        </Toolbar.Button>
        <Toolbar.Button
          tooltip="リンクを確認"
          onClick={() => {
            window.open(editor.getAttributes('fileBlock').href, '_blank', 'noopener,noreferrer');
          }}
          aria-label="リンクを確認"
          disabled={!editor.getAttributes('fileBlock').href}
        >
          <Icon name="visibility" />
        </Toolbar.Button>
        <Toolbar.Button
          type="button"
          tooltip="別タブで開く"
          aria-label="別タブで開く"
          onClick={() => {
            editor.chain().focus(undefined, { scrollIntoView: false }).toggleFileBlockTarget().run();
          }}
          active={editor.getAttributes('fileBlock').target === '_blank'}
        >
          <Icon name="open_in_new" />
        </Toolbar.Button>
      </Toolbar>
      <MediaInsert
        isOpen={isInsertModalOpen}
        onInsert={handleInsertMedia}
        onClose={handleModalClose}
        tab={tab}
        files={files}
        filetype="file"
        radioMode
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

export default FileBlockMenu;
