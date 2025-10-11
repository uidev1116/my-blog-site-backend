import { useCallback, useEffect, useMemo } from 'react';
import { Editor, NodeViewWrapper, NodeViewProps } from '@tiptap/react';
import MediaInsert from '@features/media/components/media-insert/media-insert';
import { MediaItem } from '@features/media/types';
import { ButtonV2 as Button } from '@components/button-v2';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { useMediaSelect, useFileUpload, useDropZone } from './hooks';
import DropZone from '../../../../../components/drop-zone/drop-zone';

export const Dropzone = ({
  getPos,
  editor,
  node,
}: {
  getPos: () => number;
  editor: Editor;
  node: NodeViewProps['node'];
}) => {
  const className = node?.attrs?.class || '';
  const { isInsertModalOpen, files, tab, uploadFile, handleModalClose, handleSelectClick } = useMediaSelect();
  const { fileInputRef, handleUploadClick, handleUploadFile } = useFileUpload({ uploader: uploadFile });
  const { onDrop } = useDropZone({ uploader: uploadFile });
  const pastedFiles = useMemo(() => {
    return node.attrs.__pastedFiles || [];
  }, [node.attrs.__pastedFiles]);
  const mediaType = useMemo(() => {
    return node.attrs.__mediaType || 'image'; // 'image' or 'file'
  }, [node.attrs.__mediaType]);

  useEffect(() => {
    if (pastedFiles.length > 0) {
      uploadFile([pastedFiles[0]]);
    }
  }, [pastedFiles, uploadFile]);

  const handleInsertMedia = useCallback(
    (items: MediaItem[]) => {
      const [item] = items;
      if (mediaType === 'image') {
        if (item.media_thumbnail) {
          editor
            .chain()
            .setMediaImageBlock(item, className)
            .deleteRange({ from: getPos(), to: getPos() })
            .focus()
            .run();
        }
      } else {
        editor.chain().setMediaFileBlock(item, className).deleteRange({ from: getPos(), to: getPos() }).focus().run();
      }
      handleModalClose();
    },
    [editor, mediaType, getPos, handleModalClose, className]
  );

  return (
    <NodeViewWrapper>
      <DropZone onComplete={onDrop}>
        <div className="acms-admin-block-editor-image-drop" style={{ width: '100%' }}>
          <p className="acms-admin-block-editor-image-drop-text">
            {mediaType === 'image' && (
              <span className="acms-admin-block-editor-image-drop-text-main">ここに画像をドラッグ</span>
            )}
            {mediaType === 'file' && (
              <span className="acms-admin-block-editor-image-drop-text-main">ここにファイルをドラッグ</span>
            )}
            <span className="acms-admin-block-editor-image-drop-text-sub">または</span>
          </p>
          <div className="acms-admin-block-editor-button-group">
            <Button onClick={handleSelectClick} variant="outlined" size="default" type="button">
              <Icon name="perm_media" />
              メディア選択
            </Button>
            <Button onClick={handleUploadClick} variant="filled" size="default" type="button">
              <Icon name="upload" />
              アップロード
            </Button>
          </div>
          <input
            id="media-upload-input"
            ref={fileInputRef}
            type="file"
            onChange={handleUploadFile}
            style={{ display: 'none' }}
          />
        </div>
      </DropZone>
      <MediaInsert
        isOpen={isInsertModalOpen}
        onInsert={handleInsertMedia}
        files={files}
        onClose={handleModalClose}
        tab={tab}
        filetype={mediaType}
        radioMode
      />
    </NodeViewWrapper>
  );
};

export default Dropzone;
