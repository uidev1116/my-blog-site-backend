import { CSSProperties, Component } from 'react';
import MediaInsert from '../media-insert/media-insert';
import MediaUpdate from '../media-update/media-update';
import DropZone, { DropZoneFileSelector, DropZoneText } from '../../../../components/drop-zone/drop-zone';
import { MediaItem, MediaType } from '../../types';
import type { ExtendedFile } from '../../../../lib/read-files';

type AcceptMediaType = Extract<MediaType, 'image' | 'file'> | 'all';
interface MediaDropAreaProps {
  /**
   * メディアID
   */
  mid: string;

  /**
   * サムネイル画像のパス
   */
  thumbnail: string;

  /**
   * キャプション
   */
  caption: string;

  /**
   * 幅
   */
  width: React.CSSProperties['width'];

  /**
   * 高さ
   */
  height: React.CSSProperties['height'];

  /**
   * メディアタイプ
   */
  mediaType?: MediaType;

  /**
   * 受け付けるメディアタイプ
   */
  accept?: AcceptMediaType;

  /**
   * メディアの変更時のコールバック
   */
  onChange: (media: MediaItem | null) => void;

  /**
   * エラー時のコールバック
   */
  onError: () => void;
}

interface MediaDropAreaState {
  isInsertModalOpen: boolean;
  isUpdateModalOpen: boolean;
  files: File[];
  landscape: boolean;
}

export default class MediaDropArea extends Component<MediaDropAreaProps, MediaDropAreaState> {
  static defaultProps = {
    accept: 'image',
  };

  constructor(props: MediaDropAreaProps) {
    super(props);
    this.state = {
      isInsertModalOpen: false,
      isUpdateModalOpen: false,
      files: [],
      landscape: true,
    };
  }

  componentDidMount() {
    const img = new Image();
    img.onload = () => {
      if (img.width < img.height) {
        this.setState({
          landscape: false,
        });
      }
    };
    img.src = this.props.thumbnail;
  }

  onComplete = (files: ExtendedFile[]) => {
    this.setState({
      files: files.map((item) => item.file),
      isInsertModalOpen: true,
    });
  };

  uploadFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files instanceof FileList) {
      this.setState({
        files: Array.from(e.target.files),
        isInsertModalOpen: true,
      });
    }
  };

  onInsert = (items: MediaItem[]) => {
    const { accept = 'image' } = this.props;
    const [item] = items;
    const fileOrImage = item.media_type === 'image' || item.media_type === 'svg' ? 'image' : 'file';
    let landscape = true;
    if (item.media_size) {
      const [sizeX, sizeY] = item.media_size.split(' , ');
      if (sizeX < sizeY) {
        landscape = false;
      }
    }
    if (accept !== 'all' && accept !== fileOrImage) {
      this.props.onError();
      this.setState({
        isInsertModalOpen: false,
      });
      return;
    }
    this.setState({
      isInsertModalOpen: false,
      landscape,
    });
    this.props.onChange(item);
  };

  onClose = () => {
    this.setState({
      isInsertModalOpen: false,
    });
  };

  onUpdateModalClose = () => {
    this.setState({
      isUpdateModalOpen: false,
    });
  };

  onUpdateModalUpdate = (item: MediaItem) => {
    let landscape = true;
    if (item.media_size) {
      const [sizeX, sizeY] = item.media_size.split(' , ');
      if (sizeX < sizeY) {
        landscape = false;
      }
    }
    this.setState({
      isUpdateModalOpen: false,
      landscape,
    });
    this.props.onChange(item);
  };

  handleRemove = () => {
    this.props.onChange(null);
  };

  openEditModal = () => {
    this.setState({
      isUpdateModalOpen: true,
    });
  };

  render() {
    const { isInsertModalOpen, isUpdateModalOpen, files } = this.state;
    const { mediaType, accept = 'image', caption, width, height, mid, thumbnail } = this.props;

    const style: CSSProperties = {};

    if (width) {
      style.width = width;
    }
    if (height) {
      style.height = height;
    }

    return (
      <>
        <DropZone onComplete={this.onComplete}>
          <div>
            {!mid && (
              <div className="acms-admin-media-unit-droparea" style={style}>
                <DropZoneText>{ACMS.i18n('media.add_new_media')}</DropZoneText>
                <DropZoneFileSelector onChange={this.uploadFile} disabled={isInsertModalOpen} />
                <DropZoneText>{ACMS.i18n('media.drop_file')}</DropZoneText>
              </div>
            )}
            {mid !== '' && (
              <div className="acms-admin-media-unit-preview-wrap">
                <div className="acms-admin-media-unit-preview-overlay" />
                <button
                  type="button"
                  className="acms-admin-media-unit-preview-remove-btn"
                  onClick={this.handleRemove}
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
                  onClick={() => this.openEditModal()}
                >
                  {ACMS.i18n('media.edit')}
                </button>
              </div>
            )}
          </div>
        </DropZone>
        <MediaInsert
          isOpen={isInsertModalOpen}
          onInsert={this.onInsert}
          files={files}
          onClose={this.onClose}
          tab="upload"
          filetype={accept}
        />
        <MediaUpdate
          isOpen={isUpdateModalOpen}
          mid={`${mid}`}
          onClose={this.onUpdateModalClose}
          onUpdate={this.onUpdateModalUpdate}
        />
      </>
    );
  }
}
