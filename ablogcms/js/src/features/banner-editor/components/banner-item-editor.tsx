import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import classNames from 'classnames';
import type { ExtendedFile } from '../../../lib/read-files';
import { BannerItem, BannerType } from '../types';
import DropZone, { DropZoneFileSelector, DropZoneText } from '../../../components/drop-zone/drop-zone';
import DraggableButton from '../../../components/draggable-button/draggable-button';
import { range } from '../../../utils';

interface BannerItemEditorProps {
  item: BannerItem;
  index: number;
  length: number;
  updateBannerItem: (id: string, key: keyof BannerItem, value: unknown) => void;
  removeBannerItem: (id: string) => void;
  changeBannerItemSort: (oldIndex: number, newIndex: number) => void;
  openInsertModal: (item: BannerItem) => void;
  openUpdateModal: (item: BannerItem) => void;
  clearMediaItem: (id: string) => void;
  scrollToActiveElement: () => void;
  addItem: (type: BannerType) => void;
  onComplete: (files: ExtendedFile[], item: BannerItem) => void;
  onFileInputChage: (files: File[], item: BannerItem) => void;
  renderOpenDate: (item: BannerItem) => React.ReactNode;
  attr1: string;
  attr2: string;
  hide1: string;
  hide2: string;
  tooltip1: string;
  tooltip2: string;
}

const BannerItemEditor = ({
  item,
  index,
  length,
  updateBannerItem,
  removeBannerItem,
  changeBannerItemSort,
  openInsertModal,
  openUpdateModal,
  clearMediaItem,
  scrollToActiveElement,
  onComplete,
  onFileInputChage,
  renderOpenDate,
  attr1,
  attr2,
  hide1,
  hide2,
  tooltip1,
  tooltip2,
}: BannerItemEditorProps) => {
  const { isDragging, attributes, listeners, setNodeRef, transform, transition, setActivatorNodeRef } = useSortable({
    id: item.id,
  });

  const style = {
    transform: transform ? CSS.Transform.toString({ ...transform, scaleX: 1, scaleY: 1 }) : undefined,
    transition,
    position: 'relative' as const,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={classNames('acms-admin-form acms-admin-banner-edit-item-wrap acms-admin-banner-sortable-editor', {
        'acms-admin-nested-private': !item.media_banner_status,
        'acms-admin-dragging': isDragging,
      })}
      key={item.id}
    >
      <DropZone
        onComplete={(files) => {
          onComplete(files, item);
        }}
      >
        <div
          className={classNames('acms-admin-banner-edit-item', {
            'acms-admin-banner-edit-item-hide': item.media_banner_status === 'false',
            'acms-admin-banner-edit-item-source': item.media_banner_type === 'source',
          })}
        >
          <div>
            <div className="acms-admin-banner-edit-header">
              <div className="acms-admin-banner-edit-header-item">
                <DraggableButton
                  ref={setActivatorNodeRef}
                  {...attributes}
                  {...listeners}
                  className="acms-admin-banner-edit-btn-draggable"
                  size="small"
                />
                <select
                  onChange={(e) => {
                    changeBannerItemSort(index, parseInt(e.target.value, 10));
                    scrollToActiveElement();
                  }}
                  value={index}
                >
                  {range(1, length + 1).map((i) => (
                    <option key={i} value={i}>
                      {i}
                    </option>
                  ))}
                </select>
                <select
                  defaultValue={item.media_banner_status === 'true' ? 'open' : 'close'}
                  onChange={(e) => {
                    if (e.target.value === 'open') {
                      updateBannerItem(item.id, 'media_banner_status', 'true');
                    } else {
                      updateBannerItem(item.id, 'media_banner_status', 'false');
                    }
                  }}
                >
                  <option value="open">{ACMS.i18n('media.open')}</option>
                  <option value="close">{ACMS.i18n('media.close')}</option>
                </select>
              </div>
              <div className="acms-admin-banner-edit-header-item">
                <div>
                  <span className="acms-admin-banner-edit-header-type-label">
                    {item.media_banner_type === 'image' ? ACMS.i18n(`media.media`) : ACMS.i18n(`media.source`)}
                  </span>
                </div>
                <div>
                  <button
                    type="button"
                    className="acms-admin-banner-edit-remove-btn"
                    onClick={() => removeBannerItem(item.id)}
                    aria-label={ACMS.i18n('media_banner.remove_banner_item')}
                  >
                    <i className="acms-admin-icon-cross" aria-hidden />
                  </button>
                </div>
              </div>
            </div>
            <div className="acms-admin-banner-edit">
              <div className="acms-admin-nested-item-inner">
                <div style={{ width: '100%' }}>
                  {item.media_banner_type === 'image' && (
                    <div className="acms-admin-banner-edit-item-inner" style={{ marginTop: '0' }}>
                      <div className="acms-admin-banner-edit-item-thumb-wrap" style={{ position: 'relative' }}>
                        <div>
                          {!item.media_banner_mid && (
                            <div className="acms-admin-banner-edit-droparea">
                              <DropZoneText>{ACMS.i18n('media.add_new_media')}</DropZoneText>
                              <DropZoneFileSelector
                                id={`input-file-media-banner-${item.id}`}
                                type="file"
                                onChange={(e) => {
                                  const files = Array.from((e.target as HTMLInputElement)?.files as FileList);
                                  onFileInputChage(files, item);
                                }}
                              />
                              <DropZoneText>{ACMS.i18n('media.drop_file')}</DropZoneText>
                            </div>
                          )}
                          {item.media_banner_mid && (
                            <div className="acms-admin-banner-edit-preview-wrap">
                              <div className="acms-admin-banner-edit-preview-overlay" />
                              <button
                                type="button"
                                className="acms-admin-banner-edit-preview-remove-btn"
                                onClick={() => clearMediaItem(item.id)}
                                aria-label={ACMS.i18n('media.clear_button_label')}
                              />
                              <div className="acms-admin-banner-edit-preview-edit-overlay" />
                              <img
                                className="acms-admin-banner-edit-preview"
                                src={`${item.media_banner_preview}`}
                                alt=""
                              />
                              <button
                                type="button"
                                className="acms-admin-media-edit-btn acms-admin-banner-edit-preview-edit-btn"
                                onClick={() => {
                                  openUpdateModal(item);
                                }}
                              >
                                {ACMS.i18n('media.edit')}
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                      <div className="acms-admin-banner-edit-content">
                        <table className="acms-admin-banner-edit-table">
                          <tbody>
                            <tr>
                              <th className="acms-admin-table-nowrap">{ACMS.i18n('media.media_id')}</th>
                              <td>
                                {item.media_banner_mid && (
                                  <span
                                    className="acms-admin-label acms-admin-label-default"
                                    style={{ marginRight: '5px' }}
                                  >
                                    {item.media_banner_mid}
                                  </span>
                                )}
                                <button type="button" className="acms-admin-btn" onClick={() => openInsertModal(item)}>
                                  {ACMS.i18n('media.select_from_media')}
                                </button>
                                &nbsp;
                              </td>
                            </tr>
                            <tr>
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.alt')}
                                <i
                                  className="acms-admin-icon-tooltip js-acms-tooltip-hover acms-admin-margin-left-mini"
                                  data-acms-tooltip={ACMS.i18n('media.tooltip_alt')}
                                />
                              </th>
                              <td>
                                <input
                                  type="text"
                                  className="acms-admin-form-width-full"
                                  id={`media_banner-item-alt-${item.id}`}
                                  value={item.media_banner_alt}
                                  onInput={(e: React.FormEvent<HTMLInputElement>) => {
                                    updateBannerItem(item.id, 'media_banner_alt', (e.target as HTMLInputElement).value);
                                  }}
                                />
                              </td>
                            </tr>
                            <tr>
                              <th className="acms-admin-table-nowrap">
                                {ACMS.i18n('media.url_link_to')}
                                <i
                                  className="acms-admin-icon-tooltip js-acms-tooltip-hover acms-admin-margin-left-mini"
                                  data-acms-tooltip={ACMS.i18n('media.tooltip_link')}
                                />
                              </th>
                              <td>
                                <input
                                  type="text"
                                  value={item.media_banner_override_link}
                                  className="acms-admin-form-width-full"
                                  placeholder={item.media_banner_link ? `メディア: ${item.media_banner_link}` : ''}
                                  onChange={(e) => {
                                    updateBannerItem(item.id, 'media_banner_override_link', e.target.value);
                                  }}
                                />
                                <div>
                                  <div className="acms-admin-form-checkbox">
                                    <input
                                      type="checkbox"
                                      checked={item.media_banner_target === 'true'}
                                      id={`media_banner-item-target-${item.id}`}
                                      value="true"
                                      onChange={() => {
                                        if (item.media_banner_target === 'true') {
                                          updateBannerItem(item.id, 'media_banner_target', '');
                                        } else {
                                          updateBannerItem(item.id, 'media_banner_target', 'true');
                                        }
                                      }}
                                    />
                                    <label htmlFor={`media_banner-item-target-${item.id}`}>
                                      <i className="acms-admin-ico-checkbox" />
                                      {ACMS.i18n('media.new_window')}
                                    </label>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            {hide1 !== 'true' && (
                              <tr>
                                <th className="acms-admin-table-nowrap">
                                  {attr1 || ACMS.i18n('media.attr1')}{' '}
                                  <i
                                    className="acms-admin-icon-tooltip js-acms-tooltip-hover acms-admin-margin-left-mini"
                                    data-acms-tooltip={tooltip1 || ACMS.i18n('media.tooltip_attr1')}
                                  />
                                </th>
                                <td>
                                  <input
                                    type="text"
                                    className="acms-admin-form-width-full"
                                    id={`media_banner-item-attr1-${item.id}`}
                                    value={item.media_banner_attr1}
                                    onInput={(e) => {
                                      updateBannerItem(
                                        item.id,
                                        'media_banner_attr1',
                                        (e.target as HTMLInputElement).value
                                      );
                                    }}
                                    style={{ marginRight: '5px' }}
                                  />
                                </td>
                              </tr>
                            )}
                            {hide2 !== 'true' && (
                              <tr>
                                <th className="acms-admin-table-nowrap">
                                  {attr2 || ACMS.i18n('media.attr2')}{' '}
                                  <i
                                    className="acms-admin-icon-tooltip js-acms-tooltip-hover acms-admin-margin-left-mini"
                                    data-acms-tooltip={tooltip2 || ACMS.i18n('media.tooltip_attr2')}
                                  />
                                </th>
                                <td>
                                  <input
                                    type="text"
                                    className="acms-admin-form-width-full"
                                    id={`media_banner-item-attr2-${item.id}`}
                                    value={item.media_banner_attr2}
                                    onInput={(e) => {
                                      updateBannerItem(
                                        item.id,
                                        'media_banner_attr2',
                                        (e.target as HTMLInputElement).value
                                      );
                                    }}
                                  />
                                </td>
                              </tr>
                            )}
                            {renderOpenDate(item)}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                  {item.media_banner_type === 'source' && (
                    <textarea
                      className="acms-admin-form-width-full"
                      style={{ height: '200px' }}
                      value={item.media_banner_source}
                      onChange={(e) => {
                        updateBannerItem(item.id, 'media_banner_source', e.target.value);
                      }}
                    />
                  )}
                  {item.media_banner_type === 'source' && (
                    <div className="acms-admin-banner-edit-item-inner">
                      <table className="acms-admin-banner-edit-table acms-admin-banner-edit-source-table">
                        <tbody>{renderOpenDate(item)}</tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </DropZone>
      <input
        type="hidden"
        name="media_banner_source[]"
        value={item.media_banner_source ? item.media_banner_source : ''}
      />
      <input
        type="hidden"
        name="media_banner_datestart[]"
        value={item.media_banner_datestart ? item.media_banner_datestart : ''}
      />
      <input
        type="hidden"
        name="media_banner_timestart[]"
        value={item.media_banner_timestart ? item.media_banner_timestart : ''}
      />
      <input
        type="hidden"
        name="media_banner_dateend[]"
        value={item.media_banner_dateend ? item.media_banner_dateend : ''}
      />
      <input
        type="hidden"
        name="media_banner_timeend[]"
        value={item.media_banner_timeend ? item.media_banner_timeend : ''}
      />
      <input type="hidden" name="media_banner_mid[]" value={item.media_banner_mid ? item.media_banner_mid : ''} />
      <input type="hidden" name="media_banner_attr1[]" value={item.media_banner_attr1 ? item.media_banner_attr1 : ''} />
      <input type="hidden" name="media_banner_attr2[]" value={item.media_banner_attr2 ? item.media_banner_attr2 : ''} />
      <input
        type="hidden"
        name="media_banner_status[]"
        value={item.media_banner_status ? item.media_banner_status : ''}
      />
      <input
        type="hidden"
        name="media_banner_target[]"
        value={item.media_banner_target ? item.media_banner_target : ''}
      />
      <input type="hidden" name="media_banner_type[]" value={item.media_banner_type} />
      <input type="hidden" name="media_banner_alt[]" value={item.media_banner_alt} />
      <input type="hidden" name="media_banner_link[]" value={item.media_banner_override_link} />
    </div>
  );
};

export default BannerItemEditor;
