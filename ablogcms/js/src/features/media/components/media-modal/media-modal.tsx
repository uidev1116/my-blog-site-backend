import { Component, createRef } from 'react';
import DOMPurify from 'dompurify';
import ResizeImage from '../../../../lib/resize-image/util';
import { fetchPdfPage } from './pdf-previewer';
import { formatBytes, dataURItoBlob } from '../../../../utils';
import CopyToClipboard from '../../../../components/copy-to-clipborad/copy-to-clipboard';
import { Tabs, TabPanel } from '../../../../components/tabs/tabs';
import Modal from '../../../../components/modal/modal';
import CreatableSelect from '../../../../components/rich-select/creatable-select';
import MediaEditModal from '../media-edit-modal/media-edit-modal';
import type { FocalPoint, MediaItem, MediaTag } from '../../types';
import { getFocalPoint } from '../../utils';
import HStack from '../../../../components/stack/h-stack';
import VisuallyHidden from '../../../../components/visually-hidden';
import { notify } from '../../../../lib/notify';
import { pending } from '../../../../lib/pending';
import AutoResizeTextarea from '../../../../components/textarea/auto-resize-textarea';

const delimiter = ',';
const resizeImage = new ResizeImage();

type MediaModalState = {
  isEditDialogOpen: boolean;
  blob: Blob | null;
  file: File | null;
  preview: string;
  pdfPreviewSrc: string;
  pdfHasPrevPage: boolean;
  pdfHasNextPage: boolean;
  pdfPage: number;
  focalPoint: FocalPoint;
  replaced: boolean; // check if the image is updated via input type="file"
  activeTab: string;
};

interface MediaModalProps
  extends Pick<
    React.ComponentPropsWithoutRef<typeof Modal>,
    'isOpen' | 'onClose' | 'onAfterClose' | 'onAfterOpen' | 'container'
  > {
  formToken: string;
  item: MediaItem | null;
  tags?: string[];
  onUpdate?: (media: MediaItem) => void;
  actions: typeof import('../../stores/actions');
}

export default class MediaModal extends Component<MediaModalProps, MediaModalState> {
  form: HTMLFormElement | null;

  filenameInputRef: React.RefObject<HTMLInputElement>;

  modalRef: React.RefObject<HTMLDivElement>;

  root: HTMLElement;

  static defaultProps = {
    tags: [],
  };

  constructor(props: MediaModalProps | Readonly<MediaModalProps>) {
    super(props);
    this.state = {
      isEditDialogOpen: false,
      file: null,
      blob: null,
      preview: props.item ? this.getDefaultPreview(props.item) : '',
      replaced: false,
      pdfPreviewSrc: '',
      pdfHasPrevPage: false,
      pdfHasNextPage: false,
      pdfPage: props.item?.media_pdf_page ? parseInt(props.item.media_pdf_page, 10) : 1,
      focalPoint: props.item ? getFocalPoint(props.item) : [50, 50],
      activeTab: 'edit',
    };
    this.root = document.createElement('div');
    (this.props.container || document.body).appendChild(this.root);

    this.form = null;
    this.modalRef = createRef();
    this.filenameInputRef = createRef();
  }

  getDefaultPreview(item: MediaItem) {
    if (item.media_type === 'file') {
      return item.media_thumbnail;
    }
    return `${item.media_edited}?date=${item.media_last_modified}`;
  }

  handleAfterClose() {
    this.props.actions.setItem(null);

    const { onAfterClose } = this.props;
    if (onAfterClose) {
      onAfterClose();
    }
  }

  handleAfterOpen() {
    const { onAfterOpen, item } = this.props;
    if (item !== null) {
      this.setState({
        focalPoint: getFocalPoint(item),
        pdfPage: parseInt(item.media_pdf_page, 10) || 1,
      });
    }
    if (onAfterOpen) {
      onAfterOpen();
    }
  }

  componentDidUpdate(prevProps: Readonly<MediaModalProps>) {
    const { item } = this.props;

    if (prevProps.item !== item && item !== null) {
      if (item.media_ext.toLowerCase() === 'pdf') {
        this.pdfPreview(item.media_permalink, parseInt(item.media_pdf_page, 10) || 1);
      } else {
        this.setState({
          preview: this.getDefaultPreview(item),
        });
      }
      this.setState({
        focalPoint: getFocalPoint(item),
        pdfPage: parseInt(item.media_pdf_page, 10) || 1,
      });
    }
  }

  setFormRef(node: HTMLFormElement) {
    this.form = node;
  }

  componentWillUnmount() {
    document.body.removeChild(this.root);
  }

  async pdfPreview(url: string, page: number) {
    const res = await fetchPdfPage(url, page);
    if (res.image !== false) {
      this.setState({
        pdfPreviewSrc: res.image,
        pdfHasPrevPage: res.hasPrev,
        pdfHasNextPage: res.hasNext,
        pdfPage: res.currentPage,
      });
    }
  }

  async pdfPrevPage(e: React.MouseEvent<HTMLButtonElement>) {
    e.preventDefault();

    const { item } = this.props;
    if (!item) return;
    const url = item.media_permalink;
    const prevPage = Math.max(1, (this.state.pdfPage || 1) - 1);
    await this.pdfPreview(url, prevPage);
  }

  async pdfNextPage(e: React.MouseEvent<HTMLButtonElement>) {
    e.preventDefault();

    const { item } = this.props;
    if (!item) return;
    const url = item.media_permalink;
    const nextPage = (this.state.pdfPage || 1) + 1;
    await this.pdfPreview(url, nextPage);
  }

  hideModal() {
    // 再度開いたときにメディアが開かないようにモーダルのURLを削除
    if ('history' in window) {
      history.replaceState(null, '', '#');
    }
    if (this.props.onClose) {
      this.props.onClose();
    }
  }

  handleClose() {
    this.hideModal();
  }

  isImageMedia(state: MediaModalState, props: MediaModalProps) {
    if (state.blob && state.blob.type.match(/image/)) {
      return true;
    }
    if (!state.replaced && props.item?.media_type === 'image') {
      return true;
    }

    return false;
  }

  isSvgMedia(state: MediaModalState, props: MediaModalProps) {
    if (state.file && state.file.type === 'image/svg+xml') {
      return true;
    }
    if (!state.replaced && props.item?.media_type === 'svg') {
      return true;
    }

    return false;
  }

  isFileMedia(state: MediaModalState, props: MediaModalProps) {
    if (state.file && state.file.type !== 'image/svg+xml') {
      return true;
    }
    if (!state.replaced && props.item?.media_type === 'file') {
      return true;
    }

    return false;
  }

  isPdfFile(state: MediaModalState, props: MediaModalProps) {
    if (state.file && state.file.type === 'application/pdf') {
      return true;
    }
    if (!state.replaced && props.item?.media_type === 'file' && props.item.media_ext.toLowerCase() === 'pdf') {
      return true;
    }

    return false;
  }

  buildFormData(postName: string) {
    const { blob, file, focalPoint, replaced, pdfPreviewSrc } = this.state;
    const formData = new FormData(this.form as HTMLFormElement);
    if (blob) {
      formData.append('media_file', blob);
    } else if (file) {
      formData.append('media_file', file);
    }
    if (Array.isArray(focalPoint) && focalPoint.length > 0) {
      formData.append('media[]', 'focal_x');
      formData.append('media[]', 'focal_y');
      formData.append('focal_x', `${focalPoint[0]}`);
      formData.append('focal_y', `${focalPoint[1]}`);
    }
    if (replaced) {
      formData.append('media[]', 'replaced');
      formData.append('replaced', 'true');
    }
    if (this.isFileMedia(this.state, this.props) && this.isPdfFile(this.state, this.props) && pdfPreviewSrc) {
      formData.append('media_pdf_thumbnail', dataURItoBlob(pdfPreviewSrc));
      formData.append('media[]', 'pdf_page');
      formData.append('pdf_page', String(this.state.pdfPage || 1));
    }
    formData.append(postName, 'true');
    formData.append('formToken', window.csrfToken);
    return formData;
  }

  updateStateOnAfterSave(mediaItem: MediaItem) {
    this.setState({
      file: null, // メディアを更新した場合はファイルをリセット。リセットしないと再度保存時にメディアを変更していない場合でもファイルが変更されるというアラートが出てしまう。
      blob: null, // メディアを更新した場合はファイルをリセット。リセットしないと再度保存時にメディアを変更していない場合でもファイルが変更されるというアラートが出てしまう。
      replaced: false,
      focalPoint: getFocalPoint(mediaItem), // ローカルで保持している焦点座標の状態を更新（再度モーダルを開いたときに更新を反映するため）
      pdfPage: parseInt(mediaItem.media_pdf_page, 10) || 1, // ローカルで保持しているPDFページの状態を更新（再度モーダルを開いたときに更新を反映するため）
    });
  }

  update(item: MediaItem) {
    const { actions } = this.props;

    const { file, blob } = this.state;
    const formData = this.buildFormData('ACMS_POST_Media_Update');
    if (file || blob) {
      const msg = file ? ACMS.i18n('media.file_changed') : ACMS.i18n('media.img_changed');
      if (!confirm(msg)) {
        return;
      }
    }
    const end = pending.splash(ACMS.i18n('media.saving'));
    const url = ACMS.Library.acmsLink({
      Query: {
        _mid: item.media_id,
      },
    });
    $.ajax({
      url,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
    }).then((res) => {
      end();
      if (res.status === 'failure') {
        if (res.message) {
          alert(res.message);
        } else {
          alert(ACMS.i18n('media.cannot_edit'));
        }
        return;
      }
      actions.updateMediaList(res as MediaItem);
      if (this.props.onUpdate) {
        this.props.onUpdate(res as MediaItem);
      }
      this.hideModal();
      this.updateStateOnAfterSave(res as MediaItem);
    });
  }

  uploadAsNew(item: MediaItem) {
    const { actions } = this.props;
    const formData = this.buildFormData('ACMS_POST_Media_UpdateAsNew');
    const end = pending.splash(ACMS.i18n('media.saving'));
    const url = ACMS.Library.acmsLink({
      Query: {
        _mid: item.media_id,
      },
    });
    $.ajax({
      url,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
    }).then((res) => {
      end();
      if (res.status === 'failure') {
        if (res.message) {
          alert(res.message);
        } else {
          alert(ACMS.i18n('media.cannot_edit'));
        }
        return;
      }
      actions.fetchMediaList();
      if (this.props.onUpdate) {
        this.props.onUpdate(res as MediaItem);
      }
      this.hideModal();
      this.updateStateOnAfterSave(res as MediaItem);
    });
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
    const { actions, item } = this.props;
    if (item === null) {
      return;
    }
    const label = tags.reduce((val, tag, idx) => {
      if (idx === 0) {
        return tag.value;
      }
      return `${val}${delimiter}${tag.value}`;
    }, '');

    actions.setItem({ ...item, media_label: label });
  }

  openEditDialog() {
    this.setState({
      isEditDialogOpen: true,
    });
  }

  blobToDataURL(blob: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
      const fr = new FileReader();
      fr.onload = (e: ProgressEvent<FileReader>) => {
        if (!(e.target instanceof FileReader)) {
          reject(new Error('Failed to load blob'));
          return;
        }
        resolve(e.target.result as string);
      };
      fr.readAsDataURL(blob);
    });
  }

  handleEditDialogClose() {
    this.setState({
      isEditDialogOpen: false,
    });
  }

  handleCrop(blob: Blob | null, focalPoint: FocalPoint) {
    if (blob) {
      this.blobToDataURL(blob).then((preview: string) => {
        if (focalPoint) {
          this.setState({
            preview,
            blob,
            focalPoint,
          });
        } else {
          this.setState({
            preview,
            blob,
          });
        }
      });
    }
    this.setState({
      isEditDialogOpen: false,
    });
  }

  async changeImage(e: React.ChangeEvent<HTMLInputElement>) {
    if (!(e.target instanceof HTMLInputElement)) {
      return;
    }
    if (!e.target.files) {
      return;
    }
    const { item } = this.props;
    if (item === null) {
      return;
    }
    const file: File = e.target.files[0];
    // eslint-disable-next-line no-nested-ternary
    const type = file.type.match(/svg/) ? 'svg' : file.type.match(/image/) ? 'image' : 'file';
    const isHeic = /(heic|heif)/i.test(file.type);

    if (item.media_type !== type) {
      alert(ACMS.i18n('media.cannnot_change_type'));
      return false;
    }
    const nextState: Partial<Pick<MediaModalState, keyof MediaModalState>> = {
      replaced: true,
    };
    if (type === 'image') {
      if (isHeic) {
        const { heicTo } = await import(/* webpackChunkName: "heic-to" */ 'heic-to');
        const result = await heicTo({
          blob: file,
          type: 'image/jpeg',
        });
        const blob = Array.isArray(result) ? result[0] : result;
        if (!(blob instanceof Blob)) {
          throw new Error('Invalid blob result from heicTo');
        }
        const preview = await this.blobToDataURL(blob);
        nextState.preview = preview;
        nextState.blob = blob;
      } else {
        const [resizeType, largeSize] = ACMS.Config.lgImg.split(':');
        const { blob, resize } = await resizeImage.getBlobFromFile(file, resizeType, parseInt(largeSize, 10));
        const preview = await this.blobToDataURL(blob);
        nextState.preview = preview;
        if (resize === false || ACMS.Config.mediaClientResize !== 'on') {
          nextState.blob = file;
        } else {
          nextState.blob = blob;
        }
      }
    } else if (type === 'svg') {
      const textData = await file.text();
      const clean = DOMPurify.sanitize(textData, { USE_PROFILES: { svg: true, svgFilters: true } });
      const reBlob = new Blob([clean], { type: 'image/svg+xml' });
      nextState.file = new File([reBlob], file.name, { type: reBlob.type });
      nextState.preview = await this.blobToDataURL(nextState.file);
    } else if (type === 'file') {
      nextState.file = file;
      if (file.type === 'application/pdf') {
        const url = URL.createObjectURL(file);
        this.pdfPreview(url, 1);
      } else {
        // Todo: ファイルタイプによってプレビュー画像を変更する（設定によって変わるのでphp/ACMS/function.php の pathIcon 関数を参照）
        nextState.preview = `${ACMS.Config.root}themes/system/images/fileicon/document.svg`;
      }
    }
    // ファイル名を更新
    if (this.filenameInputRef.current) {
      this.filenameInputRef.current.value = file.name;
    }
    this.setState(nextState as MediaModalState);
  }

  imageWidth(size: string) {
    const [width] = size.split(' x ');
    return parseInt(width, 10);
  }

  imageHeight(size: string) {
    const [, height] = size.split(' x ');
    return parseInt(height, 10);
  }

  render() {
    const { item, formToken, tags, isOpen } = this.props;
    const { isEditDialogOpen, preview, pdfHasPrevPage, pdfHasNextPage, pdfPreviewSrc, focalPoint } = this.state;

    return (
      <>
        <Modal
          ref={this.modalRef}
          isOpen={isOpen}
          onClose={this.handleClose.bind(this)}
          size="large"
          container={this.root}
          onAfterClose={this.handleAfterClose.bind(this)}
          onAfterOpen={this.handleAfterOpen.bind(this)}
          aria-labelledby="acms-media-modal-title"
        >
          <Modal.Header>{ACMS.i18n('media.media_edit')}</Modal.Header>
          <Modal.Body>
            {item && (
              <form className="acms-admin-form" ref={this.setFormRef.bind(this)}>
                <div className="acms-admin-media-modal-layout">
                  <div className="acms-admin-media-modal-preview">
                    {this.isImageMedia(this.state, this.props) && (
                      <div className="acms-admin-media-thumb-container acms-admin-margin-bottom-small">
                        {item.media_ext !== 'svg' && (
                          <button
                            className="acms-admin-media-edit-btn acms-admin-media-edit-thumb-btn"
                            type="button"
                            onClick={this.openEditDialog.bind(this)}
                          >
                            {ACMS.i18n('media.edit')}
                          </button>
                        )}
                        <div style={{ backgroundImage: `url("${preview}")` }} className="acms-admin-media-thumb" />
                      </div>
                    )}
                    {this.isSvgMedia(this.state, this.props) && (
                      <div className="acms-admin-media-thumb-container acms-admin-margin-bottom-small acms-admin-media-thumb-container-assets">
                        <div className="acms-admin-media-thumb-assets" style={{ width: '80%' }}>
                          <img
                            src={preview}
                            style={{
                              margin: 'auto',
                              display: 'block',
                              width: 'auto',
                              maxWidth: '100%',
                              objectFit: 'contain',
                            }}
                            alt=""
                          />
                        </div>
                      </div>
                    )}
                    {this.isFileMedia(this.state, this.props) &&
                      (this.isPdfFile(this.state, this.props) ? (
                        <div className="acms-admin-media-thumb-container acms-admin-margin-bottom-small acms-admin-media-thumb-container-assets">
                          <div className="acms-admin-media-thumb-assets" style={{ width: '70%' }}>
                            <img
                              src={pdfPreviewSrc}
                              className="acms-admin-media-thumb-box"
                              style={{
                                margin: 'auto',
                                display: 'block',
                                width: 'auto',
                                maxWidth: '100%',
                                objectFit: 'contain',
                              }}
                              alt=""
                            />
                          </div>
                          <div style={{ marginTop: '5px' }} className="acms-admin-media-thumb-pager">
                            {pdfHasPrevPage && (
                              <button
                                type="button"
                                className="acms-admin-media-thumb-pager-arrow acms-admin-media-thumb-pager-arrow-left"
                                onClick={this.pdfPrevPage.bind(this)}
                              >
                                <span className="acms-admin-icon-arrow-small-left" />
                                <VisuallyHidden>前ページ</VisuallyHidden>
                              </button>
                            )}
                            {pdfHasNextPage && (
                              <button
                                type="button"
                                className="acms-admin-media-thumb-pager-arrow acms-admin-media-thumb-pager-arrow-right"
                                onClick={this.pdfNextPage.bind(this)}
                              >
                                <span className="acms-admin-icon-arrow-small-right" />
                                <VisuallyHidden>次ページ</VisuallyHidden>
                              </button>
                            )}
                          </div>
                        </div>
                      ) : (
                        <div className="acms-admin-media-thumb-container acms-admin-margin-bottom-small acms-admin-media-thumb-container-assets">
                          <div className="acms-admin-media-thumb-assets" style={{ width: '70%' }}>
                            <img
                              className="acms-admin-media-thumb-box"
                              src={preview}
                              style={{ margin: 'auto', display: 'block', width: '70px' }}
                              alt=""
                            />
                          </div>
                        </div>
                      ))}
                  </div>

                  <div className="acms-admin-media-modal-info">
                    <Tabs>
                      <TabPanel label={ACMS.i18n('media.edit')} id="media-edit">
                        <table className="acms-admin-media-table-edit">
                          <tbody>
                            <tr>
                              <th>
                                {ACMS.i18n('media.media_change')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.managed_media_change')}
                                />
                              </th>
                              <td>
                                {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
                                <label>
                                  <input type="file" onChange={this.changeImage.bind(this)} />
                                </label>
                              </td>
                            </tr>
                            {item.media_type === 'file' && (
                              <tr>
                                <th className="acms-admin-table-nowrap">
                                  {ACMS.i18n('media.status')}
                                  <i
                                    className="acms-admin-icon-tooltip js-acms-tooltip-hover"
                                    data-acms-position="top"
                                    data-acms-tooltip={ACMS.i18n('media.status_settings')}
                                  />
                                </th>
                                <td>
                                  <select
                                    name="status"
                                    defaultValue={item.media_status}
                                    style={{
                                      maxWidth: 'max-content',
                                      width: '100%',
                                    }}
                                  >
                                    <option value="entry">{ACMS.i18n('media.follow_media_status')}</option>
                                    <option value="open">{ACMS.i18n('media.open')}</option>
                                    <option value="close">{ACMS.i18n('media.close')}</option>
                                    <option value="secret">{ACMS.i18n('media.secret')}</option>
                                  </select>
                                  <input type="hidden" name="media[]" value="status" />
                                </td>
                              </tr>
                            )}
                            <tr
                              style={{
                                display:
                                  typeof ACMS !== 'undefined' &&
                                  ACMS.Config &&
                                  ACMS.Config.mediaShowAltAndCaptionOnModal
                                    ? 'table-row'
                                    : 'none',
                              }}
                            >
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.filename')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.filename_settings')}
                                />
                              </th>
                              <td>
                                <input
                                  ref={this.filenameInputRef}
                                  type="text"
                                  defaultValue={item.media_title}
                                  name="file_name"
                                  className="acms-admin-form-width-full"
                                />
                                <input type="hidden" name="media[]" value="file_name" />
                              </td>
                            </tr>
                            <tr>
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.tag')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.managed_tag')}
                                />
                              </th>
                              <td>
                                <CreatableSelect<MediaTag, true>
                                  isMulti
                                  value={this.makeTags(item.media_label)}
                                  className="acms-admin-form-width-full"
                                  onChange={this.addTags.bind(this)}
                                  options={
                                    tags &&
                                    tags.map((tag) => ({
                                      label: tag,
                                      value: tag,
                                    }))
                                  }
                                  placeholder={ACMS.i18n('media.tag_select_placeholder')}
                                  noOptionsMessage={() => ACMS.i18n('media.tag_select_no_options_message')}
                                  formatCreateLabel={(inputValue) => ACMS.i18n('media.add_tag', { name: inputValue })}
                                  isValidNewOption={(inputValue) => inputValue.trim().length > 0}
                                  closeMenuOnSelect={false}
                                />
                                <input type="hidden" value={item.media_label} name="media_label" />
                                <input type="hidden" name="media[]" value="media_label" />
                              </td>
                            </tr>
                            <tr
                              style={{
                                display:
                                  typeof ACMS !== 'undefined' &&
                                  ACMS.Config &&
                                  ACMS.Config.mediaShowAltAndCaptionOnModal
                                    ? 'table-row'
                                    : 'none',
                              }}
                            >
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.caption')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.caption_settings')}
                                />
                              </th>
                              <td>
                                <input
                                  type="text"
                                  defaultValue={item.media_caption}
                                  name="field_1"
                                  className="acms-admin-form-width-full"
                                />
                                <input type="hidden" name="media[]" value="field_1" />
                              </td>
                            </tr>
                            <tr
                              style={{
                                display:
                                  typeof ACMS !== 'undefined' &&
                                  ACMS.Config &&
                                  ACMS.Config.mediaShowAltAndCaptionOnModal
                                    ? 'table-row'
                                    : 'none',
                              }}
                            >
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.alt')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.alt_settings')}
                                />
                              </th>
                              <td>
                                <AutoResizeTextarea
                                  defaultValue={item.media_alt}
                                  name="field_3"
                                  className="acms-admin-form-width-full"
                                />
                                <input type="hidden" name="media[]" value="field_3" />
                              </td>
                            </tr>
                            {item.media_type !== 'file' && (
                              <tr>
                                <th className="acms-admin-table-nowrap">
                                  {ACMS.i18n('media.link')}
                                  <i
                                    className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                    data-acms-position="top"
                                    data-acms-tooltip={ACMS.i18n('media.link_settings')}
                                  />
                                </th>
                                <td>
                                  <input
                                    type="text"
                                    defaultValue={item.media_link}
                                    name="field_2"
                                    className="acms-admin-form-width-full"
                                  />
                                  <input type="hidden" name="media[]" value="field_2" />
                                </td>
                              </tr>
                            )}
                            <tr>
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.memo')}
                                <i
                                  className="acms-admin-icon-tooltip acms-admin-margin-left-mini js-acms-tooltip-hover"
                                  data-acms-position="top"
                                  data-acms-tooltip={ACMS.i18n('media.memo_settings')}
                                />
                              </th>
                              <td>
                                <textarea
                                  defaultValue={item.media_text}
                                  name="field_4"
                                  className="acms-admin-form-width-full"
                                />
                                <input type="hidden" name="media[]" value="field_4" />
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </TabPanel>
                      <TabPanel label={ACMS.i18n('media.info')} id="media-info">
                        <table className="acms-admin-media-table-edit">
                          <tbody>
                            <tr>
                              <th>{ACMS.i18n('media.media_id')}</th>
                              <td>
                                <span className="acms-admin-label">{item.media_id}</span>
                              </td>
                            </tr>
                            <tr>
                              <th>{ACMS.i18n('media.upload_date')}</th>
                              <td>
                                {item.media_datetime}
                                <input type="hidden" name="upload_date" value={item.media_datetime} />
                                <input type="hidden" name="media[]" value="upload_date" />
                              </td>
                            </tr>
                            <tr>
                              <th>{ACMS.i18n('media.user_name')}</th>
                              <td>
                                {item.media_user_name}
                                <input type="hidden" name="user_id" value={item.media_user_id} />
                                <input type="hidden" name="media[]" value="user_id" />
                              </td>
                            </tr>
                            <tr>
                              <th>{ACMS.i18n('media.last_modified')}</th>
                              <td>{item.media_last_modified}</td>
                            </tr>
                            {item.media_last_update_user_name !== '' && (
                              <tr>
                                <th>{ACMS.i18n('media.last_update_user_name')}</th>
                                <td>
                                  {item.media_last_update_user_name}
                                  <input
                                    type="hidden"
                                    name="last_update_user_id"
                                    value={item.media_last_update_user_id}
                                  />
                                  <input type="hidden" name="media[]" value="last_update_user_id" />
                                </td>
                              </tr>
                            )}
                            {item.media_type === 'image' && (
                              <tr>
                                <th>{ACMS.i18n('media.image_size')}</th>
                                <td>{item.media_size} px</td>
                              </tr>
                            )}
                            <tr>
                              <th>{ACMS.i18n('media.file_size')}</th>
                              <td>{formatBytes(item.media_filesize)}</td>
                            </tr>
                          </tbody>
                        </table>
                      </TabPanel>
                    </Tabs>
                    <div className="acms-admin-media-clipboard">
                      <table className="acms-admin-table-admin-media-edit">
                        <tbody>
                          {this.isFileMedia(this.state, this.props) && (
                            <tr>
                              <th className="acms-admin-table-nowrap">{ACMS.i18n('media.entity_path')}</th>
                              <td>
                                <div className="acms-admin-form-action">
                                  <input
                                    type="text"
                                    value={decodeURI(item.media_path)}
                                    readOnly
                                    className="acms-admin-form-width-full"
                                    disabled
                                    style={{
                                      cursor: 'auto',
                                    }}
                                  />
                                  <CopyToClipboard
                                    text={item.media_path}
                                    onCopy={() => {
                                      notify.info(ACMS.i18n('media.intrinsic_link_copied'));
                                    }}
                                  >
                                    <span className="acms-admin-form-side-btn">
                                      <button type="button" className="acms-admin-btn">
                                        {ACMS.i18n('media.copy')}
                                      </button>
                                    </span>
                                  </CopyToClipboard>
                                </div>
                                <p style={{ fontSize: '12px', marginTop: '5px' }} className="acms-admin-text-primary">
                                  {ACMS.i18n('media.entity_path_attention')}
                                </p>
                              </td>
                            </tr>
                          )}
                          <tr>
                            <th className="acms-admin-table-nowrap">{ACMS.i18n('media.permalink')}</th>
                            <td>
                              <div className="acms-admin-form-action">
                                <input
                                  type="text"
                                  value={item.media_permalink}
                                  readOnly
                                  className="acms-admin-form-width-full"
                                  disabled
                                  style={{
                                    cursor: 'auto',
                                  }}
                                />
                                <CopyToClipboard
                                  text={item.media_permalink}
                                  onCopy={() => {
                                    notify.info(ACMS.i18n('media.permalink_copied'));
                                  }}
                                >
                                  <span className="acms-admin-form-side-btn">
                                    <button type="button" className="acms-admin-btn">
                                      {ACMS.i18n('media.copy')}
                                    </button>
                                  </span>
                                </CopyToClipboard>
                              </div>
                              <p
                                style={{ fontSize: '12px', marginTop: '5px', marginBottom: 0 }}
                                className="acms-admin-text-danger"
                              >
                                {ACMS.i18n('media.permalink_attention')}
                              </p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <input type="hidden" name="formToken" value={formToken} />
                {item.media_ext && <input type="hidden" name="extension" value={item.media_ext.toUpperCase()} />}
                <input type="hidden" name="type" value={item.media_type} />
                <input type="hidden" name="media[]" value="extension" />
                <input type="hidden" name="media[]" value="type" />
                <input type="hidden" name="file_size" value={item.media_size} />
                <input type="hidden" name="media[]" value="file_size" />
                <input type="hidden" name="path" value={item.media_path} />
                <input type="hidden" name="media[]" value="path" />
              </form>
            )}
          </Modal.Body>
          <Modal.Footer className="acms-admin-media-modal-footer">
            <HStack>
              <button
                type="button"
                className="acms-admin-btn acms-admin-media-cancel-btn"
                onClick={this.hideModal.bind(this)}
              >
                {ACMS.i18n('media.cancel')}
              </button>
              <button
                type="button"
                className="acms-admin-btn acms-admin-btn-primary"
                onClick={() => {
                  if (item) {
                    this.uploadAsNew(item);
                  }
                }}
              >
                {ACMS.i18n('media.save_as_new')}
              </button>
              <button
                type="button"
                className="acms-admin-btn acms-admin-btn-primary"
                onClick={() => {
                  if (item) {
                    this.update(item);
                  }
                }}
              >
                {ACMS.i18n('media.save')}
              </button>
            </HStack>
          </Modal.Footer>
        </Modal>
        {item && (
          <MediaEditModal
            isOpen={isEditDialogOpen}
            onClose={this.handleEditDialogClose.bind(this)}
            onCrop={this.handleCrop.bind(this)}
            src={preview || item.media_edited}
            original={item.media_original}
            focalPoint={focalPoint}
            width={this.imageWidth(item.media_size)}
            height={this.imageHeight(item.media_size)}
            mediaCropSizes={ACMS.Config.mediaCropSizes}
            container={this.root}
          />
        )}
      </>
    );
  }
}
