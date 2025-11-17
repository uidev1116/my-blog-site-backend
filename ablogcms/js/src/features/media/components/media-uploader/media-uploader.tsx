import { Component } from 'react';
import classNames from 'classnames';
import DOMPurify from 'dompurify';
import CreatableSelect from '../../../../components/rich-select/creatable-select';
import { MediaAjaxConfig, MediaItem, MediaTag } from '../../types';
import axiosLib from '../../../../lib/axios';
import DropZone from '../../../../components/drop-zone/drop-zone';
import ProgressBar from '../../../../components/progress-bar/progress-bar';
import ResizeImage from '../../../../lib/resize-image/util';
import { random, getExt, dataURItoBlob } from '../../../../utils';
import createPdf from '../../../../utils/createPdf';
import readFiles, { type ExtendedFile } from '../../../../lib/read-files';
import * as actions from '../../stores/actions';

const delimiter = ',';

interface MediaUploadItem {
  file: File;
  filetype: string;
  preview: string;
  blob?: Blob | null; // for heic
  id: string;
  idx: number;
  progress: number;
  progressError: boolean;
  name: string;
  size: number;
  uploadedItem?: MediaItem;
}

interface MediaUploaderProps {
  showUploadButton?: boolean;
  largeSize: number;
  config: MediaAjaxConfig;
  actions: typeof actions;
  files?: File[];
  tags?: string[];
  label?: string;
}

interface MediaUploaderState {
  items: MediaUploadItem[];
  label: string;
  loading: boolean;
  imgsOnStage: boolean;
  hasUploadedItems: boolean;
}

export default class MediaUploader extends Component<MediaUploaderProps, MediaUploaderState> {
  static defaultProps = {
    showUploadButton: true,
    tags: [],
    files: [],
  };

  constructor(props: MediaUploaderProps) {
    super(props);
    this.state = {
      items: [],
      label: props.label || '',
      loading: false,
      imgsOnStage: false,
      hasUploadedItems: false, // has already uploaded images
    };
  }

  componentDidMount() {
    if (this.props.files) {
      readFiles(this.props.files).then((extededFiles) => {
        this.onComplete(extededFiles);
      });
    }
  }

  async createPdfThumbnail(file: Blob | File) {
    const pdf = await createPdf(file, 1);
    return pdf;
  }

  upload(blob: Blob | File, label: string, name: string, item: MediaUploadItem): Promise<MediaItem> {
    const { largeSize } = this.props;
    // eslint-disable-next-line no-async-promise-executor
    return new Promise(async (resolve) => {
      const fd = new FormData();
      fd.append('ACMS_POST_Media_Upload', 'true');
      if (label) {
        fd.append('tags', label);
      }
      const ext = getExt(name);
      if (ext.toLowerCase() === 'pdf') {
        const image = await this.createPdfThumbnail(blob);
        if (image) {
          fd.append('media_pdf_thumbnail', dataURItoBlob(image));
          fd.append('pdf_page', '1');
        }
      }
      const randomName = random(10);
      fd.append('name', `${randomName}.${ext}`);
      fd.append('size', `${largeSize}`);
      fd.append('file', blob, name);
      fd.append('formToken', window.csrfToken);
      this.setProgressBar(item, 0);
      axiosLib
        .post(location.href, fd, {
          onUploadProgress: (e) => {
            if (typeof e.total === 'number') {
              this.setProgressBar(item, 70 * (e.loaded / e.total));
            }
          },
        })
        .then((res) => {
          if (res && res.data) {
            if (res.data.status === 'failure') {
              throw new Error(res.data.message);
            }
            this.setProgressBar(item, 100);
            setTimeout(() => {
              resolve(res.data);
            }, 300);
            return;
          }
          throw new Error(ACMS.i18n('media.error.unknown'));
        })
        .catch((err) => {
          console.error(err); // eslint-disable-line no-console
          this.setProgressBar(item, 100, true);
          if (err instanceof Error) {
            alert(err.message);
          }
        });
    });
  }

  setProgressBar(item: MediaUploadItem, progress: number, progressError = false) {
    const { items } = this.state;
    const findIndex = items.findIndex((file) => {
      if (file.id === item.id) {
        return true;
      }
      return false;
    });
    const renewedItem = {
      ...item,
      progress,
      progressError,
    };
    this.setState({
      items: [...items.slice(0, findIndex), renewedItem, ...items.slice(findIndex + 1)],
    });
  }

  removeFile(file: MediaUploadItem) {
    const { items } = this.state;
    const index = items.findIndex((item) => {
      if (item.idx === file.idx) {
        return true;
      }
      return false;
    });
    this.setState({
      items: [...items.slice(0, index), ...items.slice(index + 1)],
    });
  }

  setUploadedItem(file: MediaUploadItem, uploadedItem: MediaItem) {
    const { items } = this.state;
    const index = items.findIndex((item) => {
      if (item.idx === file.idx) {
        return true;
      }
      return false;
    });
    const selectedItem = items[index];
    const updatedItem = {
      ...selectedItem,
      uploadedItem,
      progress: -1,
    };

    // eslint-disable-next-line react/no-access-state-in-setstate
    const copyItems = this.state.items.slice();
    copyItems[index] = updatedItem;

    this.setState({
      items: copyItems,
    });
  }

  editItem(item: MediaUploadItem) {
    if (item.uploadedItem) {
      this.props.actions.setItem(item.uploadedItem);
    }
  }

  uploadItems(): Promise<MediaItem[]> {
    return new Promise((resolve) => {
      const { items, label } = this.state;
      const { actions } = this.props;
      const promises: Promise<MediaItem>[] = [];
      const resizeImage = new ResizeImage();
      this.setState({
        loading: true,
        hasUploadedItems: true,
      });
      const [resizeType, largeSize] = ACMS.Config.lgImg.split(':');

      [].forEach.call(items, (item: MediaUploadItem) => {
        const { name, filetype, file } = item;
        const isHeic = filetype === 'image' && /(heic|heif)/i.test(file.type);
        const isSvg = filetype === 'image' && file.type.includes('svg');
        const isGif = filetype === 'image' && file.type.includes('gif');

        const promise = new Promise<MediaItem>((resolve) => {
          if (filetype === 'image') {
            if (isHeic) {
              // heic は blob があるのでそれをアップロード
              if (item.blob instanceof Blob) {
                this.upload(item.blob, label, name, item).then((uploadedItem) => {
                  this.setUploadedItem(item, uploadedItem);
                  resolve(uploadedItem);
                });
              }
            } else if (isSvg) {
              // svg は text を取得して DOMPurify でクリーンアップしてからアップロード
              file.text().then((text) => {
                const clean = DOMPurify.sanitize(text, { USE_PROFILES: { svg: true, svgFilters: true } });
                const reBlob = new Blob([clean], { type: 'image/svg+xml' });
                this.upload(reBlob, label, name, item).then((uploadedItem) => {
                  this.setUploadedItem(item, uploadedItem);
                  resolve(uploadedItem);
                });
              });
            } else if (isGif) {
              // gif はリサイズせずそのままアップロード
              this.upload(file, label, name, item).then((uploadedItem) => {
                this.setUploadedItem(item, uploadedItem);
                resolve(uploadedItem);
              });
            } else {
              // それ以外はリサイズしてアップロード
              resizeImage.getBlobFromFile(file, resizeType, parseInt(largeSize, 10)).then(({ blob, resize }) => {
                const uploadData = resize && ACMS.Config.mediaClientResize !== 'off' ? blob : file;
                this.upload(uploadData, label, name, item).then((uploadedItem) => {
                  this.setUploadedItem(item, uploadedItem);
                  resolve(uploadedItem);
                });
              });
            }
          } else {
            // ファイルはそのままアップロード
            this.upload(file, label, name, item).then((uploadedItem) => {
              this.setUploadedItem(item, uploadedItem);
              resolve(uploadedItem);
            });
          }
        });
        promises.push(promise);
      });
      Promise.all(promises).then((results) => {
        this.setState({
          loading: false,
        });
        actions.fetchMediaList();
        resolve(results);
      });
    });
  }

  onComplete(files: ExtendedFile[]) {
    let items: MediaUploadItem[] = [
      ...this.state.items,
      ...files.map(
        (file) =>
          ({
            file: file.file,
            filetype: file.filetype,
            preview: file.filetype === 'image' ? file.preview : '',
            blob: file.filetype === 'image' ? file.blob : null, // for heic
            name: file.file.name,
            size: file.file.size,
            idx: -1,
            id: '',
            progress: 0,
            progressError: false,
          }) as MediaUploadItem
      ),
    ]
      .filter((item) => {
        if (item.uploadedItem) {
          return false;
        }
        return true;
      })
      .map((item, index) => ({
        ...item,
        idx: index,
        id: random(10),
      }));
    // 重複を削除
    items = Array.from(new Map(items.map((item) => [item.file.name, item])).values());

    if (items.length > 20) {
      alert(ACMS.i18n('media.more_than_20_not_allowed'));
      items = items.slice(0, 20);
    }
    if (items.length > 0) {
      this.setState({ items, hasUploadedItems: false, imgsOnStage: true });
    }
  }

  makeTags(label: string) {
    if (!label) {
      return null;
    }
    const labels = label.split(delimiter);
    return labels.map((label) => ({
      value: label,
      label,
    }));
  }

  addTags(tags: readonly MediaTag[]) {
    const label = tags.reduce((val, tag, idx) => {
      if (idx === 0) {
        return tag.value;
      }
      return `${val}${delimiter}${tag.value}`;
    }, '');

    this.setState({
      label,
    });
  }

  // eslint-disable-next-line react/no-unused-class-component-methods
  removeUploadedItems() {
    const { items } = this.state;
    const newItems = items.filter((item) => !item.uploadedItem);
    this.setState({
      items: newItems,
    });
  }

  render() {
    const { items, label, hasUploadedItems, imgsOnStage } = this.state;
    const { showUploadButton, tags } = this.props;
    return (
      <>
        <div className="clearfix" style={{ marginBottom: '10px' }}>
          <div style={{ padding: '0 10px' }}>
            <CreatableSelect<MediaTag, true>
              isMulti
              options={(tags || []).map((tag) => ({
                label: tag,
                value: tag,
              }))}
              value={this.makeTags(label)}
              onChange={this.addTags.bind(this)}
              placeholder={ACMS.i18n('media.tag_select_placeholder')}
              formatCreateLabel={(inputValue) => ACMS.i18n('media.add_tag', { name: inputValue })}
              isValidNewOption={(inputValue) => inputValue.trim().length > 0}
              noOptionsMessage={() => ACMS.i18n('media.tag_select_no_options_message')}
              closeMenuOnSelect={false}
              menuPortalTarget={document.body}
            />
          </div>
        </div>
        <div className="acms-admin-media-upload-container-wrap">
          <div className="acms-admin-media-upload-container">
            <div className="acms-admin-media-upload-item">
              <div className="acms-admin-media-upload-item-inner">
                <DropZone onComplete={this.onComplete.bind(this)} />
              </div>
            </div>
            {items.map((item) => (
              <div className="acms-admin-media-upload-item" key={item.id}>
                <div
                  className={classNames('acms-admin-media-upload-item-inner', {
                    '-progress': item.progress > 0,
                  })}
                  style={{ position: 'relative' }}
                >
                  {item.progress > 0 && (
                    <div
                      style={{
                        position: 'absolute',
                        top: '0',
                        left: '0',
                        width: '100%',
                        boxSizing: 'border-box',
                        zIndex: 2,
                      }}
                    >
                      <ProgressBar progress={item.progress} alert={item.progressError} />
                    </div>
                  )}
                  {!item.uploadedItem && (
                    <button
                      type="button"
                      className="acms-admin-media-upload-cancel"
                      onClick={this.removeFile.bind(this, item)}
                      aria-label={ACMS.i18n('media.remove_file')}
                    />
                  )}
                  {item.uploadedItem && (
                    <button
                      type="button"
                      className="acms-admin-media-edit-btn acms-admin-media-edit-upload-btn"
                      onClick={this.editItem.bind(this, item)}
                    >
                      {ACMS.i18n('media.edit')}
                    </button>
                  )}
                  {item.filetype === 'image' && (
                    <div className="acms-admin-media-upload-bg" style={{ backgroundImage: `url(${item.preview})` }} />
                  )}
                  {item.filetype !== 'image' && (
                    <div className="acms-admin-media-upload-bg">
                      <img
                        src={`${ACMS.Config.root}themes/system/images/fileicon/file.png`}
                        className="acms-admin-media-upload-file"
                        alt=""
                      />
                    </div>
                  )}
                  <div className="acms-admin-media-overlay" />
                  <div className="acms-admin-media-upload-caption">
                    <p className="acms-admin-media-upload-caption-text">{item.name}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
        {items.length === 0 && <p style={{ textAlign: 'center' }}>{ACMS.i18n('media.no_staged_media')}</p>}
        {showUploadButton && imgsOnStage && (
          <div className="clearfix">
            <button
              type="button"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-float-right"
              onClick={this.uploadItems.bind(this)}
              disabled={hasUploadedItems}
            >
              {ACMS.i18n('media.upload')}
            </button>
          </div>
        )}
      </>
    );
  }
}
