import { Component, createRef } from 'react';
import MediaInsert from '../media-insert/media-insert';
import MediaUpdate from '../media-update/media-update';
import DropZone, { DropZoneFileSelector, DropZoneText } from '../../../../components/drop-zone/drop-zone';
import AutoResizeTextarea from '../../../../components/textarea/auto-resize-textarea';
import { MediaItem } from '../../types';
import { ExtendedFile } from '../../../../lib/read-files';

type MediaUnitProps = {
  items?: MediaItem[];
  mediaSizes?: { value: string; label: string; selected: boolean }[];
  primaryImageId: string;
  id: string;
  active: string;
  mediaDir: string;
  lang?: string;
  usePdfIcon: 'yes' | 'no';
  primary: 'true' | 'false';
  hasLink: 'true' | 'false';
  multiUpload: 'true' | 'false';
  enlarged: 'true' | 'false';
  overrideLink: string;
  overrideAlt: string;
  overrideCaption: string;
  onMount?: () => void;
  onChange?: (mediaItems: MediaItem[]) => void;
};

type MediaUnitState = {
  insertModalOpened: boolean;
  updateModalOpened: boolean;
  newMedia: boolean;
  noLink: 'yes' | 'no';
  noLarge: 'yes' | 'no';
  useIcon: 'yes' | 'no';
  modalType: 'upload' | 'select';
  link: string;
  targetId: string | null;
  items: MediaItem[];
  files: File[];
  landscape: boolean;
  alt: string;
  caption: string;
};

export default class MediaUnit extends Component<MediaUnitProps, MediaUnitState> {
  static defaultProps = {
    items: [],
    mediaSizes: [],
    lang: 'ja',
  };

  rootRef = createRef<HTMLDivElement>();

  constructor(props: MediaUnitProps) {
    super(props);

    let newMedia = false;
    if (props.items && !props.items[0].media_id) {
      newMedia = true;
    }
    this.state = {
      insertModalOpened: false,
      updateModalOpened: false,
      modalType: 'upload',
      targetId: null,
      items: props.items || [],
      files: [],
      landscape: true,
      newMedia,
      noLarge: props.enlarged === 'true' ? 'no' : 'yes',
      noLink: props.hasLink === 'true' ? 'no' : 'yes',
      useIcon: props.usePdfIcon === 'yes' ? 'yes' : 'no',
      link: props.overrideLink,
      alt: props.overrideAlt,
      caption: props.overrideCaption,
    };
  }

  componentDidMount() {
    const { items, newMedia } = this.state;
    if (items.length > 0 && !newMedia) {
      const [item] = items;
      const img = new Image();
      img.onload = () => {
        if (img.width < img.height) {
          this.setState({
            landscape: false,
          });
        }
      };
      img.src = item.media_thumbnail;
    }

    if (this.props.onMount) {
      this.props.onMount();
    }
  }

  componentDidUpdate(_: MediaUnitProps, prevState: MediaUnitState) {
    if (prevState.items !== this.state.items && this.props.onChange) {
      this.props.onChange(this.state.items);
    }
  }

  openInsertModal = () => {
    this.setState({
      modalType: 'select',
      insertModalOpened: true,
    });
  };

  onInsert = (items: MediaItem[]) => {
    this.setState({
      items,
      insertModalOpened: false,
    });
  };

  onClose = () => {
    this.setState({
      insertModalOpened: false,
    });
  };

  onUpdateModalClose = () => {
    this.setState({
      updateModalOpened: false,
    });
  };

  onUpdateModalUpdate = (item: MediaItem) => {
    const { items, targetId } = this.state;
    const findIndex = items.findIndex((currentItem) => {
      if (currentItem.media_id === targetId) {
        return true;
      }
      return false;
    });
    const updated = [...items.slice(0, findIndex), item, ...items.slice(findIndex + 1)];
    this.setState({
      updateModalOpened: false,
      items: updated,
    });
  };

  removeMediaAt = (index: number) => {
    const { items } = this.state;
    const filtered = items.filter((item, i) => i !== index);
    this.setState({
      items: filtered,
    });
  };

  openEditModal = (targetId: string) => {
    this.setState({
      targetId,
      updateModalOpened: true,
    });
  };

  onComplete = (files: ExtendedFile[]) => {
    this.setState({
      files: files.map((item) => item.file),
      insertModalOpened: true,
      modalType: 'upload',
    });
  };

  uploadFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!(e.target.files instanceof FileList)) {
      return;
    }
    this.setState({
      files: Array.from(e.target.files),
      insertModalOpened: true,
      modalType: 'upload',
    });
  };

  render() {
    const {
      insertModalOpened,
      updateModalOpened,
      modalType,
      targetId,
      items,
      files,
      newMedia,
      noLarge,
      useIcon,
      link,
      alt,
      caption,
    } = this.state;
    const { mediaSizes, primaryImageId, id, active, enlarged, usePdfIcon, primary, lang, multiUpload } = this.props;
    const [item] = items;

    return (
      <div ref={this.rootRef}>
        <DropZone
          onComplete={(files) => {
            this.onComplete(files);
          }}
        >
          <table className="entryFormColumnSettingTable entryFormColumnTable">
            <tbody>
              <tr className="entryFormFileControl">
                <td className="acms-admin-media-unit-preview-area">
                  {(items.length === 0 || (items.length < 2 && !item.media_id)) && (
                    <div>
                      <div className="acms-admin-media-unit-droparea">
                        <DropZoneText>{ACMS.i18n('media.add_new_media')}</DropZoneText>
                        <DropZoneFileSelector onChange={this.uploadFile} disabled={insertModalOpened} />
                        <DropZoneText>{ACMS.i18n('media.drop_file')}</DropZoneText>
                      </div>
                      <input type="hidden" name={`media_id_${id}[]`} value="" />
                    </div>
                  )}
                  {items.length > 0 && items.length < 2 && item.media_id && (
                    <div className="acms-admin-media-unit-preview-wrap">
                      <div className="acms-admin-media-unit-preview-overlay" />
                      <button
                        type="button"
                        className="acms-admin-media-unit-preview-remove-btn"
                        onClick={() => this.removeMediaAt(0)}
                        aria-label={ACMS.i18n('media.clear_button_label')}
                      />
                      <div className="acms-admin-media-unit-preview-edit-overlay" />
                      {(item.media_type === 'image' || item.media_type === 'svg') && (
                        <img className="acms-admin-media-unit-preview" src={`${item.media_thumbnail}`} alt="" />
                      )}
                      {item.media_type === 'file' && (
                        <div className="acms-admin-media-unit-file-icon-wrap">
                          <img className="acms-admin-media-unit-file-icon" src={`${item.media_thumbnail}`} alt="" />
                          <p className="acms-admin-media-unit-file-caption">{item.media_title}</p>
                        </div>
                      )}
                      <button
                        type="button"
                        className="acms-admin-media-edit-btn acms-admin-media-unit-preview-edit-btn"
                        onClick={() => this.openEditModal(item.media_id)}
                      >
                        {ACMS.i18n('media.edit')}
                      </button>
                      <input type="hidden" name={`media_id_${id}[]`} value={item && item.media_id} />
                    </div>
                  )}
                </td>
                <td className="entryFormFileControl">
                  <table className="acms-admin-margin-bottom-mini" style={{ width: '100%' }}>
                    <tbody>
                      <tr>
                        <th>ID</th>
                        <td>
                          <div style={{ display: 'inline-block' }}>
                            {item && item.media_id && (
                              <span className="acms-admin-label acms-admin-label-default acms-admin-margin-right-mini">
                                {item.media_id}
                              </span>
                            )}
                          </div>
                          <button type="button" className="acms-admin-btn-admin" onClick={this.openInsertModal}>
                            {ACMS.i18n('media.select_from_media')}
                          </button>
                          {active !== 'on' && <span style={{ color: 'red' }}>{ACMS.i18n('media.media_unit_use')}</span>}
                        </td>
                      </tr>
                      {mediaSizes && mediaSizes.length !== 0 && (
                        <tr>
                          <th>
                            <label htmlFor={`unit-media-size-${id}`}>{ACMS.i18n('media.size')}</label>
                          </th>
                          <td>
                            <select
                              defaultValue={mediaSizes.find((size) => size.selected)?.value}
                              key={mediaSizes.find((size) => size.selected)?.value}
                              name={`media_size_${id}[]`}
                              id={`unit-media-size-${id}`}
                            >
                              {mediaSizes.map((mediaSize) => (
                                <option value={mediaSize.value} key={mediaSize.label}>
                                  {mediaSize.label}
                                </option>
                              ))}
                            </select>
                            {item &&
                              item.media_type === 'file' &&
                              (item.media_pdf === 'yes' ||
                                (item.media_ext && item.media_ext.toUpperCase() === 'PDF')) && (
                                <div className="acms-admin-form-checkbox" style={{ marginLeft: '10px' }}>
                                  <input type="hidden" name={`media_use_icon_${id}[]`} value={useIcon} />
                                  <input
                                    type="checkbox"
                                    value="yes"
                                    id={`input-checkbox-media_use_icon_${id}_${lang}`}
                                    defaultChecked={usePdfIcon === 'yes'}
                                    onChange={(e) => {
                                      if (e.target.checked) {
                                        this.setState({
                                          useIcon: 'yes',
                                        });
                                      } else {
                                        this.setState({
                                          useIcon: 'no',
                                        });
                                      }
                                    }}
                                  />
                                  <label htmlFor={`input-checkbox-media_use_icon_${id}_${lang}`}>
                                    <i className="acms-admin-ico-checkbox" />
                                    {ACMS.i18n('media.use_icon')}
                                  </label>
                                </div>
                              )}
                          </td>
                        </tr>
                      )}
                      {items.length < 2 && (
                        <tr>
                          <th>
                            <label htmlFor={`unit-media-caption-${id}`}>{ACMS.i18n('media.caption')}</label>
                          </th>
                          <td>
                            <input
                              type="text"
                              name={`media_caption_${id}[]`}
                              id={`unit-media-caption-${id}`}
                              className="acms-admin-form-width-full"
                              defaultValue={caption}
                              placeholder={item && item.media_caption ? `${item.media_caption}` : ''}
                            />
                          </td>
                        </tr>
                      )}
                      {items.length < 2 && (
                        <tr>
                          <th>
                            <label htmlFor={`unit-media-alt-text-${id}`}>{ACMS.i18n('media.alt')}</label>
                          </th>
                          <td>
                            <AutoResizeTextarea
                              name={`media_alt_${id}[]`}
                              defaultValue={alt}
                              id={`unit-media-alt-text-${id}`}
                              className="acms-admin-form-width-full"
                              placeholder={item && item.media_alt ? `${item.media_alt}` : ''}
                              maxRows={4}
                            />
                          </td>
                        </tr>
                      )}
                      {items.length < 2 &&
                        item &&
                        item.media_id !== '' &&
                        (item.media_type === 'image' || item.media_type === 'svg') && (
                          <tr>
                            <th>
                              <label htmlFor={`input-text-media_link_${id}_${lang}`}>
                                {ACMS.i18n('media.url_link_to')}
                              </label>
                            </th>
                            <td>
                              <input
                                type="text"
                                id={`input-text-media_link_${id}_${lang}`}
                                name={`media_link_${id}[]`}
                                className="acms-admin-form-width-full"
                                placeholder={item.media_link || ''}
                                defaultValue={link}
                              />
                            </td>
                          </tr>
                        )}
                      {items.length < 2 && item && (item.media_type === 'image' || item.media_type === 'svg') && (
                        <tr>
                          <th>
                            <label htmlFor={`input-checkbox-media_enlarged_${id}_${lang}`}>
                              {ACMS.i18n('media.image_link')}
                            </label>
                          </th>
                          <td>
                            <div className="acms-admin-form-checkbox">
                              <input type="hidden" name={`media_enlarged_${id}[]`} value={noLarge} />
                              <input
                                type="checkbox"
                                value="no"
                                id={`input-checkbox-media_enlarged_${id}_${lang}`}
                                defaultChecked={enlarged === 'true'}
                                onChange={(e) => {
                                  if (e.target.checked) {
                                    this.setState({
                                      noLarge: 'no',
                                    });
                                  } else {
                                    this.setState({
                                      noLarge: 'yes',
                                    });
                                  }
                                }}
                              />
                              <label htmlFor={`input-checkbox-media_enlarged_${id}_${lang}`}>
                                <i className="acms-admin-ico-checkbox" />
                                {ACMS.i18n('media.no_image_link')}
                              </label>
                            </div>
                          </td>
                        </tr>
                      )}
                      {items.length < 2 &&
                        primaryImageId !== 'no' &&
                        item &&
                        item.media_type === 'image' &&
                        !newMedia && (
                          <tr>
                            <th>
                              <label htmlFor={`input-radio-image_primary_image_${id}_${lang}`}>
                                {ACMS.i18n('media.main_image')}
                              </label>
                            </th>
                            <td>
                              <div className="acms-admin-form-radio">
                                <input
                                  type="radio"
                                  name="primary_image"
                                  value={primaryImageId}
                                  id={`input-radio-image_primary_image_${id}_${lang}`}
                                  defaultChecked={primary === 'true'}
                                />
                                <label htmlFor={`input-radio-image_primary_image_${id}_${lang}`}>
                                  <i className="acms-admin-ico-radio" />
                                  {ACMS.i18n('media.set_as_main_image')}
                                </label>
                              </div>
                            </td>
                          </tr>
                        )}
                    </tbody>
                  </table>
                  {!(items.length < 2 && item && (item.media_type === 'image' || item.media_type === 'svg')) && (
                    <input type="hidden" name={`media_enlarged_${id}[]`} value="no" />
                  )}
                </td>
              </tr>
              {items.length >= 2 && (
                <tr>
                  <td colSpan={2}>
                    <div className="acms-admin-media-unit-preview-group">
                      {items.map((item, index) => (
                        <div
                          className="acms-admin-media-unit-preview-wrap acms-admin-media-unit-preview-group-item"
                          key={item.media_id}
                        >
                          <div className="acms-admin-media-unit-preview-group-item-inner">
                            <div className="acms-admin-media-unit-preview-edit-overlay" />
                            {(item.media_type === 'image' || item.media_type === 'svg') && (
                              <img
                                className="acms-admin-media-unit-preview-group-img"
                                src={`${item.media_thumbnail}`}
                                alt=""
                              />
                            )}
                            {item.media_type === 'file' && (
                              <div className="acms-admin-media-unit-file-icon-wrap">
                                <img
                                  className="acms-admin-media-unit-file-icon"
                                  src={`${item.media_thumbnail}`}
                                  alt=""
                                />
                                <p className="acms-admin-media-unit-file-caption">{item.media_title}</p>
                              </div>
                            )}
                            <input type="hidden" name={`media_id_${id}[]`} value={item && item.media_id} />
                          </div>
                          <div className="acms-admin-media-unit-preview-overlay" />
                          <button
                            type="button"
                            className="acms-admin-media-unit-preview-remove-btn"
                            onClick={() => this.removeMediaAt(index)}
                            aria-label={ACMS.i18n('media.clear_button_label')}
                          />
                          <button
                            type="button"
                            className="acms-admin-media-edit-btn acms-admin-media-unit-preview-edit-btn"
                            onClick={() => this.openEditModal(item.media_id)}
                          >
                            {ACMS.i18n('media.edit')}
                          </button>
                        </div>
                      ))}
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
          <MediaInsert
            isOpen={insertModalOpened}
            onInsert={this.onInsert}
            radioMode={multiUpload === 'false'}
            files={files}
            onClose={this.onClose}
            tab={modalType}
            filetype="all"
          />
          {targetId && (
            <MediaUpdate
              isOpen={updateModalOpened}
              mid={targetId}
              onClose={this.onUpdateModalClose}
              onUpdate={this.onUpdateModalUpdate}
            />
          )}
        </DropZone>
      </div>
    );
  }
}
