import { useState, useCallback, useRef } from 'react';
import MediaInsert from '../../../../media/components/media-insert/media-insert';
import MediaUpdate from '../../../../media/components/media-update/media-update';
import DropZone from '../../../../../components/drop-zone/drop-zone';
import type { MediaItem, MediaViewFileType } from '../../../../media/types';
import type { ExtendedFile } from '../../../../../lib/read-files';

interface DropAreaProps {
  mid?: string;
  thumbnail?: string;
  caption?: string;
  className?: string;
  mediaType?: 'image' | 'file' | 'all' | 'svg';
  onChange?: (items: MediaItem[]) => void;
  onError?: () => void;
}

const DropArea = ({
  mid: midProp = '',
  thumbnail: thumbnailProp = '',
  caption = '',
  className = '',
  mediaType = 'image',
  onChange = () => {}, // eslint-disable-line @typescript-eslint/no-empty-function
  onError = () => {}, // eslint-disable-line @typescript-eslint/no-empty-function
}: DropAreaProps) => {
  const [mid, setMid] = useState(midProp);
  const [tab, setTab] = useState<'upload' | 'select'>('upload');
  const [insertModalOpened, setInsertModalOpened] = useState(false);
  const [updateModalOpened, setUpdateModalOpened] = useState(false);
  const [files, setFiles] = useState<File[]>([]);
  const [thumbnail, setThumbnail] = useState(thumbnailProp);
  const inputFileRef = useRef<HTMLInputElement>(null);

  const handleUpdateButtonClick = useCallback(() => {
    inputFileRef.current?.click();
  }, []);

  const handleComplete = useCallback((files: ExtendedFile[]) => {
    setFiles(files.map((item) => item.file));
    setInsertModalOpened(true);
  }, []);

  const uploadFile = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    if (!(event.target instanceof HTMLInputElement)) {
      return;
    }
    if (!event.target.files) {
      return;
    }
    setFiles(Array.from(event.target.files));
    setInsertModalOpened(true);
  }, []);

  const handleInsert = useCallback(
    (items: MediaItem[]) => {
      const [item] = items;
      const fileOrImage = item.media_type === 'image' || item.media_type === 'svg' ? 'image' : 'file';
      if (mediaType !== 'all' && mediaType !== fileOrImage) {
        onError();
        setInsertModalOpened(false);
        return;
      }
      setInsertModalOpened(false);
      setMid(item.media_id);
      setThumbnail(item.media_edited);
      onChange(items);
    },
    [mediaType, onError, onChange]
  );

  const onClose = useCallback(() => {
    setInsertModalOpened(false);
  }, []);

  const handleUpdateModalClose = useCallback(() => {
    setUpdateModalOpened(false);
  }, []);

  const selectMedia = useCallback(() => {
    setInsertModalOpened(true);
    setTab('select');
  }, []);

  const handleMediaUpdate = useCallback(
    (item: MediaItem) => {
      setUpdateModalOpened(false);
      setMid(item.media_id);
      setThumbnail(item.media_edited);
      onChange([item]);
    },
    [onChange]
  );

  const remove = useCallback(() => {
    setMid('');
    onChange([]);
  }, [onChange]);

  const openEditModal = useCallback(() => {
    setUpdateModalOpened(true);
  }, []);

  return (
    <>
      <DropZone onComplete={handleComplete}>
        <div>
          {!mid && (
            <div className={`acms-admin-editor-media-box ${className}`}>
              <div className="acms-admin-editor-media-icon-wrap">
                <i className="acms-admin-icon acms-admin-icon-unit-image" />
              </div>
              <p className="acms-admin-media-unit-droparea-text">新規メディアを追加 または ファイルをドロップ</p>
              <div style={{ marginTop: '5px' }}>
                <button type="button" className="acms-admin-editor-media-btn" onClick={handleUpdateButtonClick}>
                  アップロード
                </button>
                <input
                  ref={inputFileRef}
                  id="input-file"
                  type="file"
                  onChange={uploadFile}
                  style={{ display: 'none' }}
                  multiple
                />
                <button type="button" className="acms-admin-editor-media-btn" onClick={selectMedia}>
                  メディアを選択
                </button>
              </div>
            </div>
          )}
          {mid && (
            <div className="acms-admin-media-unit-preview-wrap">
              <div className="acms-admin-media-unit-preview-overlay" />
              <button
                type="button"
                className="acms-admin-media-unit-preview-remove-btn"
                onClick={remove}
                aria-label={ACMS.i18n('media.clear_button_label')}
              />
              {(mediaType === 'image' || mediaType === 'svg') && (
                <img className="acms-admin-media-field-preview" src={`${thumbnail}`} alt="" />
              )}
              {mediaType === 'file' && (
                <div className="acms-admin-media-unit-file-icon-wrap">
                  <img className="acms-admin-media-unit-file-icon" src={`${thumbnail}`} alt="" />
                  <p className="acms-admin-media-unit-file-caption">{caption}</p>
                </div>
              )}
              <button
                type="button"
                className="acms-admin-media-edit-btn acms-admin-media-unit-preview-edit-btn"
                onClick={openEditModal}
              >
                編集
              </button>
            </div>
          )}
        </div>
      </DropZone>
      <MediaInsert
        isOpen={insertModalOpened}
        onInsert={handleInsert}
        files={files}
        onClose={onClose}
        tab={tab}
        filetype={['image', 'svg'].includes(mediaType) ? 'image' : (mediaType as MediaViewFileType)}
      />
      <MediaUpdate
        isOpen={updateModalOpened}
        mid={`${mid}`}
        onClose={handleUpdateModalClose}
        onUpdate={handleMediaUpdate}
      />
    </>
  );
};

export default DropArea;
