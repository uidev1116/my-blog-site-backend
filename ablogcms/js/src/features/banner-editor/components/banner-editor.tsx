/* eslint camelcase: 0 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  SortableContext,
  arraySwap,
  arrayMove,
  verticalListSortingStrategy,
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { restrictToVerticalAxis, restrictToWindowEdges } from '@dnd-kit/modifiers';
import {
  DndContext,
  DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import dayjs from 'dayjs';
import DatePicker from '../../../components/date-picker/date-picker';
import TimePicker from '../../../components/time-picker/time-picker';
import { random, getOffset } from '../../../utils';
import { MediaItem } from '../../media/types';
import type { ExtendedFile } from '../../../lib/read-files';
import Menu from './menu';
import BannerItemEditor from './banner-item-editor';
import MediaInsert from '../../media/components/media-insert/media-insert';
import MediaUpdate from '../../media/components/media-update/media-update';
import type { BannerItem, BannerType, SortableBannerItem } from '../types';

interface BannerEditorProps {
  attr1: string;
  attr2: string;
  tooltip1: string;
  tooltip2: string;
  hide1: string;
  hide2: string;
  items: BannerItem[];
}

const createBannerItem = (data: Partial<BannerItem> = {}): BannerItem => ({
  id: random(10),
  media_banner_type: 'image',
  media_banner_attr1: '',
  media_banner_attr2: '',
  media_banner_source: '',
  media_banner_status: 'true',
  media_banner_target: 'false',
  media_banner_preview: '',
  media_banner_datestart: dayjs().format('YYYY-MM-DD'),
  media_banner_timestart: '00:00:00',
  media_banner_dateend: '9999-12-31',
  media_banner_timeend: '23:59:59',
  media_banner_mid: '',
  media_banner_alt: '',
  media_banner_link: '',
  media_banner_override_link: '',
  toggle: true,
  ...data,
});

const createSortableBannerItem = (data: Partial<BannerItem>): SortableBannerItem => {
  const id = data.id || random(10);
  return {
    id,
    data: createBannerItem({ ...data, id }),
  };
};

const mergeMediaItemToBannerItem = (mediaItem: MediaItem, bannerItem: BannerItem): BannerItem => {
  let media_banner_landscape = true;

  if (mediaItem.media_size) {
    const [sizeX, sizeY] = mediaItem.media_size.split(' , ');
    if (sizeX < sizeY) {
      media_banner_landscape = false;
    }
  }

  return {
    ...bannerItem,
    media_banner_mid: mediaItem.media_id,
    media_banner_preview: mediaItem.media_thumbnail,
    media_banner_link: mediaItem.media_link,
    media_banner_landscape,
  };
};

const BannerEditor = (props: BannerEditorProps) => {
  const [insertModalOpened, setInsertModalOpened] = useState(false);
  const [updateModalOpened, setUpdateModalOpened] = useState(false);
  const [targetItem, setTargetItem] = useState<BannerItem | null>(null);
  const [modalType, setModalType] = useState<'select' | 'upload'>('upload');
  const [sortableItems, setSortableItems] = useState<SortableBannerItem[]>([
    {
      id: random(10),
      data: null,
    },
    ...props.items.map(createBannerItem).map((item) => ({
      id: item.id,
      data: item,
    })),
  ]);
  const [files, setFiles] = useState<File[]>([]);
  const [changed, setChanged] = useState(false);

  const items = useMemo(
    () => sortableItems.filter((item) => item.data !== null).map((item) => item.data) as BannerItem[],
    [sortableItems]
  );

  const addItem = useCallback((type: BannerType) => {
    const newItem = createSortableBannerItem({ media_banner_type: type });
    setSortableItems((prevItems) => {
      const unitBoxIndex = prevItems.findIndex((item) => item.data === null);
      return [...prevItems.slice(0, unitBoxIndex + 1), newItem, ...prevItems.slice(unitBoxIndex + 1)];
    });
    setChanged(true);
  }, []);

  const removeBannerItem = (id: string) => {
    setSortableItems((prevItems) => prevItems.filter((item) => item.id !== id));
    setChanged(true);
  };

  const updateBannerItem = (id: string, key: keyof BannerItem, value: unknown) => {
    setSortableItems((prevItems) =>
      prevItems.map((item) =>
        item.id === id ? ({ id, data: { ...item.data, [key]: value } } as SortableBannerItem) : item
      )
    );
    setChanged(true);
  };

  const openInsertModal = (item: BannerItem) => {
    setTargetItem(item);
    setModalType('select');
    setInsertModalOpened(true);
  };

  const openUploadModal = (item: BannerItem) => {
    setTargetItem(item);
    setModalType('upload');
    setInsertModalOpened(true);
  };

  const openUpdateModal = (item: BannerItem) => {
    setTargetItem(item);
    setUpdateModalOpened(true);
  };

  const handleMediaInsert = (newItems: MediaItem[]) => {
    if (targetItem === null) {
      return;
    }
    const index = sortableItems.findIndex((item) => item.id === targetItem.id);
    const newBannerItem = mergeMediaItemToBannerItem(newItems[0], targetItem);
    const newSortableItem = createSortableBannerItem(newBannerItem);

    let newSortableItems = sortableItems.map((sortableItem) =>
      sortableItem.id === targetItem.id ? newSortableItem : sortableItem
    );

    if (newItems.length > 1) {
      const firstItems = [...newSortableItems.slice(0, index + 1)];
      const lastItems = [...newSortableItems.slice(index + 1)];
      const middleItems = [
        ...newItems
          .slice(1)
          .map((item) => createSortableBannerItem(mergeMediaItemToBannerItem(item, createBannerItem()))),
      ];
      newSortableItems = [...firstItems, ...middleItems, ...lastItems];
    }

    setSortableItems(newSortableItems);
    setInsertModalOpened(false);
    setChanged(true);
    setFiles([]);
  };

  const handleMediaUpdate = (item: MediaItem) => {
    if (targetItem === null) {
      return;
    }
    const newBannerItem = mergeMediaItemToBannerItem(item, targetItem);
    const newSortableItem = createSortableBannerItem(newBannerItem);
    const newSortableItems = sortableItems.map((sortableItem) =>
      sortableItem.id === newSortableItem.id ? newSortableItem : sortableItem
    );
    setSortableItems(newSortableItems);
    setUpdateModalOpened(false);
    setChanged(true);
  };

  const handleMediaInsertModalClose = () => {
    setInsertModalOpened(false);
    setFiles([]);
  };

  const handleUpdateModalClose = () => {
    setUpdateModalOpened(false);
  };

  const scrollToActiveElement = () => {
    setTimeout(() => {
      const { activeElement } = document;
      if (!activeElement) {
        return;
      }
      const offset = getOffset(activeElement);
      window.scrollTo(0, offset.top);
    }, 100);
  };

  const renderOpenDate = (item: BannerItem) => (
    <tr>
      <th>
        {ACMS.i18n('media.open_period')}
        <i
          className="acms-admin-icon-tooltip js-acms-tooltip-hover acms-admin-margin-left-mini"
          data-acms-tooltip={ACMS.i18n('media.banner_open_date')}
        />
      </th>
      <td>
        <DatePicker
          style={{ maxWidth: '95px' }}
          value={item.media_banner_datestart}
          onChange={(_dates: Date[], date: string) => {
            updateBannerItem(item.id, 'media_banner_datestart', date);
          }}
        />
        &nbsp;
        <TimePicker
          style={{ maxWidth: '95px' }}
          value={item.media_banner_timestart}
          onChange={(_dates: Date[], time: string) => {
            updateBannerItem(item.id, 'media_banner_timestart', time);
          }}
        />
        &nbsp; 〜 &nbsp;
        <DatePicker
          style={{ maxWidth: '95px' }}
          value={item.media_banner_dateend}
          onChange={(_dates: Date[], date: string) => {
            updateBannerItem(item.id, 'media_banner_dateend', date);
          }}
        />
        &nbsp;
        <TimePicker
          style={{ maxWidth: '95px' }}
          value={item.media_banner_timeend}
          onChange={(_dates: Date[], time: string) => {
            updateBannerItem(item.id, 'media_banner_timeend', time);
          }}
        />
      </td>
    </tr>
  );

  const clearMediaItem = (id: string) => {
    setSortableItems((prevItems) =>
      prevItems.map((item) =>
        item.id === id ? ({ id, data: { ...item.data, media_banner_mid: '' } } as SortableBannerItem) : item
      )
    );
    setChanged(true);
  };

  const handleComplete = useCallback((files: ExtendedFile[], item: BannerItem) => {
    setFiles(files.map((file) => file.file));
    openUploadModal(item);
  }, []);

  const handleFileInputChage = useCallback((files: File[], item: BannerItem) => {
    setFiles(files);
    openUploadModal(item);
  }, []);

  useEffect(() => {
    if (items.length < 1) {
      addItem('image');
    }
  }, [items, addItem]);

  useEffect(() => {
    if (changed) {
      $('.js-config-not-saved').addClass('active');
    }
  }, [changed]);

  const changeBannerItemSort = useCallback(
    (oldIndex: number, newIndex: number) => {
      const from = sortableItems.findIndex((item) => item.id === items[oldIndex - 1].id);
      const to = sortableItems.findIndex((item) => item.id === items[newIndex - 1].id);
      const newSortableItems = arraySwap(sortableItems, from, to);
      setSortableItems(newSortableItems);
      setChanged(true);
    },
    [items, sortableItems]
  );

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = useCallback(
    ({ active, over }: DragEndEvent) => {
      if (over && active.id !== over.id) {
        const oldIndex = sortableItems.findIndex((item) => item.id === active.id);
        const newIndex = sortableItems.findIndex((item) => item.id === over.id);
        const newSortableItems = arrayMove(sortableItems, oldIndex, newIndex);
        setSortableItems(newSortableItems);
        setChanged(true);
      }
    },
    [sortableItems]
  );

  return (
    <div>
      <div className="acms-admin-banner-edit-container">
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
          modifiers={[restrictToVerticalAxis, restrictToWindowEdges]}
        >
          <SortableContext items={sortableItems} strategy={verticalListSortingStrategy}>
            {sortableItems.map((sortableItem) => {
              if (sortableItem.data) {
                return (
                  <BannerItemEditor
                    key={sortableItem.id}
                    item={sortableItem.data}
                    index={items.findIndex((item) => item.id === sortableItem.id) + 1}
                    length={items.length}
                    updateBannerItem={updateBannerItem}
                    changeBannerItemSort={changeBannerItemSort}
                    removeBannerItem={removeBannerItem}
                    openInsertModal={openInsertModal}
                    openUpdateModal={openUpdateModal}
                    clearMediaItem={clearMediaItem}
                    scrollToActiveElement={scrollToActiveElement}
                    addItem={addItem}
                    onComplete={handleComplete}
                    onFileInputChage={handleFileInputChage}
                    renderOpenDate={renderOpenDate}
                    attr1={props.attr1}
                    attr2={props.attr2}
                    hide1={props.hide1}
                    hide2={props.hide2}
                    tooltip1={props.tooltip1}
                    tooltip2={props.tooltip2}
                  />
                );
              }
              return <Menu key={sortableItem.id} id={sortableItem.id} addItem={addItem} />;
            })}
          </SortableContext>
        </DndContext>
      </div>
      <input type="hidden" name="@media_banner[]" value="media_banner_source" />
      <input type="hidden" name="config[]" value="media_banner_source" />
      <input type="hidden" name="@media_banner[]" value="media_banner_datestart" />
      <input type="hidden" name="config[]" value="media_banner_datestart" />
      <input type="hidden" name="@media_banner[]" value="media_banner_timestart" />
      <input type="hidden" name="config[]" value="media_banner_timestart" />
      <input type="hidden" name="@media_banner[]" value="media_banner_dateend" />
      <input type="hidden" name="config[]" value="media_banner_dateend" />
      <input type="hidden" name="@media_banner[]" value="media_banner_timeend" />
      <input type="hidden" name="config[]" value="media_banner_timeend" />
      <input type="hidden" name="@media_banner[]" value="media_banner_mid" />
      <input type="hidden" name="config[]" value="media_banner_mid" />
      <input type="hidden" name="@media_banner[]" value="media_banner_attr1" />
      <input type="hidden" name="config[]" value="media_banner_attr1" />
      <input type="hidden" name="@media_banner[]" value="media_banner_attr2" />
      <input type="hidden" name="config[]" value="media_banner_attr2" />
      <input type="hidden" name="@media_banner[]" value="media_banner_status" />
      <input type="hidden" name="config[]" value="media_banner_status" />
      <input type="hidden" name="@media_banner[]" value="media_banner_target" />
      <input type="hidden" name="config[]" value="media_banner_target" />
      <input type="hidden" name="@media_banner[]" value="media_banner_type" />
      <input type="hidden" name="config[]" value="media_banner_type" />
      <input type="hidden" name="config[]" value="@media_banner" />
      <input type="hidden" name="config[]" value="media_banner_alt" />
      <input type="hidden" name="@media_banner[]" value="media_banner_alt" />
      <input type="hidden" name="config[]" value="media_banner_link" />
      <input type="hidden" name="@media_banner[]" value="media_banner_link" />
      <MediaInsert
        isOpen={insertModalOpened}
        onInsert={handleMediaInsert}
        onClose={handleMediaInsertModalClose}
        tab={modalType}
        files={files}
        filetype="image"
      />
      {targetItem && (
        <MediaUpdate
          isOpen={updateModalOpened}
          mid={targetItem.media_banner_mid}
          onClose={handleUpdateModalClose}
          onUpdate={handleMediaUpdate}
        />
      )}
    </div>
  );
};

export default BannerEditor;
