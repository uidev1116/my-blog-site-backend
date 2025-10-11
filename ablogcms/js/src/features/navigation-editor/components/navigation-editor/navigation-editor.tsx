import React, { Fragment, useState, useCallback } from 'react';
import Nestable from 'react-nestable';
import classNames from 'classnames';
import MediaInsert from '@features/media/components/media-insert/media-insert';
import MediaDropArea from '@features/media/components/media-droparea/media-droparea';
import type { MediaItem, MediaType } from '@features/media/types';
import { useMediaSelect } from '@hooks/use-media';
import { Tooltip } from '../../../../components/tooltip';
import DraggableButton from '../../../../components/draggable-button/draggable-button';
import { useNestableEdit, type BaseNestableItem } from './hooks';

import 'react-nestable/dist/styles/index.css';

interface NavigationItem extends BaseNestableItem {
  navigation_label: string;
  navigation_uri: string;
  navigation_attr: string;
  navigation_a_attr: string;
  navigation_target: boolean;
  navigation_publish: boolean;
  navigation_media: string;
  navigation_media_type: MediaType;
  navigation_media_thumbnail: string;
  toggle: boolean;
  hide: boolean;
}

interface NavigationEditorProps {
  items: NavigationItem[];
  enableMedia?: boolean;
  message: {
    open: string;
    label: string;
    detail: string;
    add: string;
    remove: string;
    attr: string;
    child_attr: string;
  };
}

export const NavigationEditor: React.FC<NavigationEditorProps> = ({ items: initialItems, enableMedia, message }) => {
  const defineDefaultItem = (): Omit<NavigationItem, keyof BaseNestableItem> => ({
    navigation_label: '',
    navigation_uri: '',
    navigation_attr: '',
    navigation_a_attr: '',
    navigation_target: false,
    navigation_publish: true,
    navigation_media: '',
    navigation_media_type: '',
    navigation_media_thumbnail: '',
    toggle: false,
    hide: true,
  });

  const { items, setItems, updateItem, addChild, removeItem, handleNestableChange, nested, reIndex } =
    useNestableEdit<NavigationItem>(initialItems, defineDefaultItem);

  const [isOpenAll, setIsOpenAll] = useState<boolean>(false);
  const { isInsertModalOpen, tab, files, handleModalClose, handleSelectClick } = useMediaSelect();
  const [currentItem, setCurrentItem] = useState<NavigationItem | null>(null);

  const handleOpenAll = () => {
    setItems(items.map((item) => ({ ...item, toggle: true })));
    setIsOpenAll(true);
  };

  const handleCloseAll = () => {
    setItems(items.map((item) => ({ ...item, toggle: false })));
    setIsOpenAll(false);
  };

  const handleInsertMedia = useCallback(
    (newMedia: MediaItem | MediaItem[] | null, item: NavigationItem | null) => {
      const media = Array.isArray(newMedia) ? newMedia[0] : newMedia;
      if (!item) {
        handleModalClose();
        return;
      }
      setItems(
        items.map((data) =>
          item.id === data.id
            ? {
                ...data,
                navigation_media: media?.media_id ?? '',
                navigation_media_type: media?.media_type ?? ('' as MediaType),
                navigation_media_thumbnail: media?.media_thumbnail ?? '',
              }
            : data
        )
      );
      handleModalClose();
    },
    [items, setItems, handleModalClose]
  );

  const getRenderItem = useCallback(
    (item: NavigationItem, collapseIcon: React.ReactNode, handler: React.ReactNode) => (
      <div
        className={classNames('acms-admin-form', {
          'acms-admin-nested-private': !item.navigation_publish,
        })}
        key={item.uuid}
        aria-label={`ナビゲーション項目 ${item.navigation_label || '未設定'}。ドラッグで並び替え可能です。`}
      >
        <div className="acms-admin-nested-item">
          <div className="acms-admin-nested-item-header clearfix">
            <div className="acms-admin-nested-item-inner">
              <div className="acms-admin-nested-item-handle">{handler}</div>
              <div className="acms-admin-nested-item-child acms-admin-nested-item-child-checkbox">
                <div className="acms-admin-form-checkbox">
                  <label>
                    <input
                      id={`navigation-item-publish-${item.uuid}`}
                      type="checkbox"
                      defaultChecked={item.navigation_publish}
                      value="on"
                      onChange={(event) => {
                        event.stopPropagation();
                        updateItem(item.id, 'navigation_publish', event.target.checked);
                      }}
                    />
                    <i className="acms-admin-ico-checkbox" />
                    {message.open}
                  </label>
                </div>
              </div>
              <div className="acms-admin-nested-item-child">
                <div className="acms-admin-form-action">
                  <label
                    className="acms-admin-form-side"
                    style={{ whiteSpace: 'nowrap' }}
                    htmlFor={`navigation-item-label-${item.uuid}`}
                  >
                    {message.label}
                  </label>
                  <input
                    type="text"
                    className="acms-admin-form-width-full"
                    id={`navigation-item-label-${item.uuid}`}
                    defaultValue={item.navigation_label}
                    aria-required="true"
                    onChange={(event) => {
                      updateItem(item.id, 'navigation_label', event.target.value);
                    }}
                  />
                </div>
              </div>
              <div className="acms-admin-nested-item-child acms-admin-nested-item-child-link">
                <div className="acms-admin-form-action">
                  <label
                    className="acms-admin-form-side"
                    style={{ whiteSpace: 'nowrap' }}
                    htmlFor={`navigation-item-url-${item.uuid}`}
                  >
                    URL
                  </label>
                  <input
                    type="text"
                    placeholder="https://example.com/"
                    className="acms-admin-nested-input"
                    id={`navigation-item-url-${item.uuid}`}
                    defaultValue={item.navigation_uri}
                    aria-required="true"
                    onChange={(event) => {
                      updateItem(item.id, 'navigation_uri', event.target.value);
                    }}
                  />
                </div>
              </div>
              <div className="acms-admin-nested-item-actions">
                {collapseIcon && (
                  <button
                    aria-expanded={item.toggle}
                    aria-controls={`detail-${item.uuid}`}
                    data-tooltip-id="navigation-tooltip"
                    data-tooltip-variant="dark"
                    data-tooltip-place="top"
                    data-tooltip-content={ACMS.i18n('navigation.show_child_items')}
                    type="button"
                    className="acms-admin-btn-admin acms-admin-nested-collapse-btn"
                  >
                    {collapseIcon}
                  </button>
                )}
                <button
                  type="button"
                  aria-expanded={item.toggle}
                  aria-controls={`detail-${item.uuid}`}
                  data-tooltip-id="navigation-tooltip"
                  data-tooltip-content={ACMS.i18n('navigation.show_details')}
                  data-tooltip-variant="dark"
                  data-tooltip-place="top"
                  className="acms-admin-btn-admin"
                  onClick={() => updateItem(item.id, 'toggle', !item.toggle)}
                >
                  {message.detail}
                </button>
                <button
                  type="button"
                  data-tooltip-id="navigation-tooltip"
                  data-tooltip-content={ACMS.i18n('navigation.add_new_item')}
                  data-tooltip-variant="dark"
                  data-tooltip-place="top"
                  onClick={() => addChild(item)}
                  className="acms-admin-btn-admin"
                >
                  {message.add}
                </button>
                <button
                  type="button"
                  className="acms-admin-btn acms-admin-btn-danger"
                  onClick={() => removeItem(item.id, ACMS.i18n('navigation.on_remove'))}
                >
                  {message.remove}
                </button>
              </div>
            </div>
          </div>
          {item.toggle && (
            <div
              className="acms-admin-nested-item-detail"
              id={`detail-${item.uuid}`}
              aria-hidden={!item.toggle}
              hidden={!item.toggle}
            >
              <div className="acms-admin-nested-item-inner acms-admin-justify-content-start">
                <div className="acms-admin-nested-item-child acms-admin-flex-column acms-admin-gap-2">
                  <div className="acms-admin-form-action">
                    <label
                      className="acms-admin-form-side"
                      htmlFor={`navigation-item-attr-${item.id}`}
                      style={{ whiteSpace: 'nowrap' }}
                    >
                      {message.attr}
                    </label>
                    <input
                      type="text"
                      className="acms-admin-nested-input"
                      id={`navigation-item-attr-${item.id}`}
                      defaultValue={item.navigation_attr}
                      onChange={(event) => {
                        updateItem(item.id, 'navigation_attr', event.target.value);
                      }}
                    />
                  </div>
                  <div className="acms-admin-form-action">
                    <label
                      className="acms-admin-form-side"
                      htmlFor={`navigation-item-child_attr-${item.id}`}
                      style={{ whiteSpace: 'nowrap' }}
                    >
                      {message.child_attr}
                    </label>
                    <input
                      type="text"
                      className="acms-admin-nested-input"
                      id={`navigation-item-child_attr-${item.id}`}
                      defaultValue={item.navigation_a_attr}
                      onChange={(event) => {
                        updateItem(item.id, 'navigation_a_attr', event.target.value);
                      }}
                    />
                  </div>
                  <div className="acms-admin-nested-item-child acms-admin-nested-item-child-checkbox">
                    <div className="acms-admin-form-checkbox">
                      <label>
                        <input
                          id={`navigation-item-target-${item.id}`}
                          type="checkbox"
                          defaultChecked={item.navigation_target}
                          value="_blank"
                          onChange={(event) => {
                            event.stopPropagation();
                            updateItem(item.id, 'navigation_target', event.target.checked);
                          }}
                        />
                        <i className="acms-admin-ico-checkbox" />
                        別タブで開く
                      </label>
                    </div>
                  </div>
                  {enableMedia && (
                    <div className="acms-admin-nested-item-media">
                      <div>
                        <MediaDropArea
                          mid={item.navigation_media}
                          thumbnail={item.navigation_media_thumbnail}
                          mediaType={item.navigation_media_type}
                          accept="image"
                          width={180}
                          height={140}
                          caption=""
                          onChange={(media) => handleInsertMedia(media, item)}
                          onError={() => {}}
                        />
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          setCurrentItem(item);
                          handleSelectClick();
                        }}
                        className="acms-admin-btn-admin"
                      >
                        メディアから選択
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    ),
    [message, enableMedia, handleInsertMedia, updateItem, addChild, removeItem, handleSelectClick]
  );

  return (
    <div>
      <div className="clearfix" style={{ paddingBottom: '10px' }}>
        {isOpenAll ? (
          <button
            type="button"
            className="acms-admin-btn acms-admin-float-right"
            onClick={handleCloseAll}
            aria-pressed="true"
          >
            {ACMS.i18n('navigation.hide_all_details')}
          </button>
        ) : (
          <button
            type="button"
            className="acms-admin-btn acms-admin-float-right"
            onClick={handleOpenAll}
            aria-pressed="false"
          >
            {ACMS.i18n('navigation.show_all_details')}
          </button>
        )}
      </div>
      <div className="acms-admin-nested-items">
        <Nestable
          items={nested}
          collapsed={false}
          renderItem={({ item, collapseIcon, handler }) => getRenderItem(item as NavigationItem, collapseIcon, handler)}
          onChange={handleNestableChange as any} // eslint-disable-line @typescript-eslint/no-explicit-any
          handler={<DraggableButton size="small" />}
        />
      </div>
      {reIndex.map((item) => (
        <Fragment key={item.id}>
          <input type="hidden" name="navigation_parent[]" value={item.parent ? item.parent : ''} />
          <input type="hidden" name="navigation_label[]" value={item.navigation_label ? item.navigation_label : ''} />
          <input type="hidden" name="navigation_attr[]" value={item.navigation_attr ? item.navigation_attr : ''} />
          <input
            type="hidden"
            name="navigation_a_attr[]"
            value={item.navigation_a_attr ? item.navigation_a_attr : ''}
          />
          <input type="hidden" name="navigation_uri[]" value={item.navigation_uri ? item.navigation_uri : ''} />
          <input type="hidden" name="navigation_publish[]" value={item.navigation_publish ? 'on' : 'off'} />
          <input type="hidden" name="navigation_target[]" value={item.navigation_target ? '_blank' : ''} />
          <input type="hidden" name="navigation_media[]" value={item.navigation_media ? item.navigation_media : ''} />
        </Fragment>
      ))}
      <input type="hidden" name="config[]" value="navigation_publish" />
      <input type="hidden" name="config[]" value="navigation_target" />
      <input type="hidden" name="config[]" value="navigation@sort" />
      <input type="hidden" name="config[]" value="navigation_label" />
      <input type="hidden" name="config[]" value="navigation_uri" />
      <input type="hidden" name="config[]" value="navigation_attr" />
      <input type="hidden" name="config[]" value="navigation_a_attr" />
      <input type="hidden" name="config[]" value="navigation_parent" />
      <input type="hidden" name="config[]" value="navigation_media" />

      <MediaInsert
        isOpen={isInsertModalOpen}
        onInsert={(media) => handleInsertMedia(media, currentItem)}
        onClose={handleModalClose}
        tab={tab}
        radioMode
        files={files}
        filetype="image"
      />

      <Tooltip id="navigation-tooltip" />
    </div>
  );
};

export default NavigationEditor;
