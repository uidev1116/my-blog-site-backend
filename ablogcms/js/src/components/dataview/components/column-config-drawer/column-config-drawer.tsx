import { useCallback, useState } from 'react';
import {
  DndContext,
  closestCenter,
  DragEndEvent,
  useSensors,
  useSensor,
  PointerSensor,
  KeyboardSensor,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { restrictToVerticalAxis, restrictToParentElement } from '@dnd-kit/modifiers';
import { CSS } from '@dnd-kit/utilities';
import classnames from 'classnames';
import { Icon } from '@components/icon';
import Drawer from '../../../drawer/drawer';
import HStack from '../../../stack/h-stack';
import VStack from '../../../stack/v-stack';
import DraggableButton from '../../../draggable-button/draggable-button';
import { type RowData, type Column, ColumnVisibility, ColumnConfig } from '../../types';
import useDisclosure from '../../../../hooks/use-disclosure';
import CustomColumnModal from '../custom-column-modal/custom-column-modal';
import useUpdateEffect from '../../../../hooks/use-update-effect';
import useColumnService from '../../stores/service/hook';
import Splash from '../../../splash/splash';
import Alert from '../../../alert/alert';
import { sortColumnsByColumnOrder, sortColumnsByColumnVisibility } from '../../utils';

interface ColumnConfigDrawerProps extends React.ComponentPropsWithoutRef<typeof Drawer> {
  customColumnModalProps?: Omit<React.ComponentPropsWithoutRef<typeof CustomColumnModal>, 'isOpen' | 'onClose'>;
}

interface ColumnToggleControlProps<T extends RowData> {
  column: Column<T>;
  checked: boolean;
  sort: number;
}

const ColumnToggleControl = <T extends RowData>({ column, checked = false, sort }: ColumnToggleControlProps<T>) => {
  const [isDraggable, setIsDraggable] = useState(checked);
  const { attributes, listeners, setNodeRef, transform, transition, setActivatorNodeRef, isDragging } = useSortable({
    id: column.id,
    disabled: !isDraggable,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  const { id } = useColumnService();

  const handleCheckboxChange = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    setIsDraggable(event.target.checked);
  }, []);

  return (
    <li
      ref={setNodeRef}
      style={style}
      className={classnames({
        'acms-admin-dragging': isDragging,
      })}
    >
      <HStack>
        <div>
          <DraggableButton ref={setActivatorNodeRef} {...listeners} {...attributes} streched disabled={!isDraggable} />
        </div>
        <div>
          <input type="hidden" name={`${id}_column_visibility[${sort}]`} value="off" />
          <div className="acms-admin-form-checkbox" style={{ zIndex: 1 }}>
            <input
              id={`input-checkbox-visibility-${column.id}`}
              type="checkbox"
              name={`${id}_column_visibility[${sort}]`}
              value="on"
              defaultChecked={checked}
              key={checked.toString()}
              onChange={handleCheckboxChange}
            />
            <label htmlFor={`input-checkbox-visibility-${column.id}`}>
              <i className="acms-admin-ico-checkbox" />
              {column.label}
            </label>
          </div>
        </div>
      </HStack>
      <input type="hidden" name={`${id}_column_id[${sort}]`} value={column.id} />
    </li>
  );
};

function sortColumns<T extends RowData = RowData>(columns: Column<T>[], config: ColumnConfig) {
  const sortedColumnsByOrder = sortColumnsByColumnOrder(columns, config.order);
  const sortedColumnsByVisibility = sortColumnsByColumnVisibility(sortedColumnsByOrder, config.visibility);
  return sortedColumnsByVisibility;
}

const ColumnConfigDrawer = ({
  onClose,
  onAfterOpen,
  customColumnModalProps = {},
  ...props
}: ColumnConfigDrawerProps) => {
  const {
    id,
    columns: globalColumns,
    config: globalConfig,
    mutateConfig,
    invalidateColumnConfig,
    invalidateCustomColumns,
  } = useColumnService();

  const getColumnVisibilityFromGlobal = useCallback(() => {
    return globalConfig.visibility;
  }, [globalConfig.visibility]);
  // グローバルなカラム表示状態をローカルなカラム表示状態に反映する
  const [columnVisibility, setColumnVisibility] = useState<ColumnVisibility>(() => getColumnVisibilityFromGlobal());

  useUpdateEffect(() => {
    setColumnVisibility(getColumnVisibilityFromGlobal());
  }, [getColumnVisibilityFromGlobal]);

  const getColumnsFromGlobal = useCallback(() => {
    const hidableColumns = globalColumns.filter(
      (column) => column.enableHiding === undefined || column.enableHiding === true
    );
    const sortedColumns = sortColumns(hidableColumns, globalConfig);
    return sortedColumns;
  }, [globalColumns, globalConfig]);

  // グローバルなカラムの状態をローカルなカラムの状態に反映する
  const [columns, setColumns] = useState(() => getColumnsFromGlobal());
  useUpdateEffect(() => {
    setColumns(getColumnsFromGlobal());
  }, [getColumnsFromGlobal]);

  const handleAfterOpen = useCallback(async () => {
    // ドロワーが開いたときにカラムの状態を最新にする
    await invalidateColumnConfig();
    await invalidateCustomColumns();
    if (onAfterOpen) {
      onAfterOpen();
    }
  }, [onAfterOpen, invalidateColumnConfig, invalidateCustomColumns]);

  const handleClose = useCallback(() => {
    onClose();
  }, [onClose]);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      setColumns((prevClumns) => {
        const oldIndex = prevClumns.findIndex((column) => column.id === active.id);
        const newIndex = prevClumns.findIndex((column) => column.id === over.id);
        return arrayMove(prevClumns, oldIndex, newIndex);
      });
    }
  }

  const {
    isOpen: isCustomColumnModalOpen,
    open: openCustomColumnModal,
    close: closeCustomColumnModal,
  } = useDisclosure({ defaultIsOpen: false });

  const handleOpenCustomColumnModal = useCallback(() => {
    openCustomColumnModal();
  }, [openCustomColumnModal]);

  const handleCloseCustomColumnModal = useCallback(() => {
    closeCustomColumnModal();
  }, [closeCustomColumnModal]);

  const [mutationStatus, setMutationStatus] = useState<'success' | 'failure' | 'pending' | 'none'>('none');

  const handleSubmit = useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      if (!(event.target instanceof HTMLFormElement)) {
        return;
      }
      event.preventDefault();
      setMutationStatus('pending');
      const result = await mutateConfig(new FormData(event.target));
      if ('errors' in result) {
        setMutationStatus('failure');
        return;
      }
      setMutationStatus('success');
      // 保存成功時にカラムの表示状態を最新にする
      if (result.data !== null) {
        await invalidateColumnConfig();
      }
    },
    [mutateConfig, invalidateColumnConfig]
  );

  useUpdateEffect(() => {
    if (mutationStatus === 'success') {
      onClose();
      setMutationStatus('none');
    }

    // onClose is not a dependency because it is not expected to change
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mutationStatus]);

  const handleAlertClose = useCallback(() => {
    setMutationStatus('none');
  }, []);

  return (
    <>
      <Drawer
        onClose={handleClose}
        onAfterOpen={handleAfterOpen}
        {...props}
        aria-labelledby="acms-column-visibility-drawer-title"
      >
        <Drawer.Header>{ACMS.i18n('dataview.column_visibility.title')}</Drawer.Header>
        <Drawer.Body>
          {mutationStatus === 'failure' && (
            <Alert type="danger" icon={<span className="acms-admin-icon-attention" />} onClose={handleAlertClose}>
              {ACMS.i18n('dataview.column_visibility.failure')}
            </Alert>
          )}
          <form id={`${id}-column-settings-form`} method="post" className="acms-admin-form" onSubmit={handleSubmit}>
            <div>
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
                modifiers={[restrictToVerticalAxis, restrictToParentElement]}
              >
                <VStack asChild align="start">
                  <ul className="acms-admin-dataview-column-list">
                    <SortableContext items={columns} strategy={verticalListSortingStrategy}>
                      {columns.map((column, index) => (
                        <ColumnToggleControl
                          key={column.id}
                          column={column}
                          checked={columnVisibility[column.id]}
                          sort={index}
                        />
                      ))}
                    </SortableContext>
                  </ul>
                </VStack>
              </DndContext>
            </div>
            <input type="hidden" name={`@${id}_column_group[]`} value={`${id}_column_id`} />
            <input type="hidden" name={`@${id}_column_group[]`} value={`${id}_column_visibility`} />
            <input type="hidden" name="config[]" value={`${id}_column_id`} />
            <input type="hidden" name="config[]" value={`${id}_column_visibility`} />
            <input type="hidden" name="config[]" value={`@${id}_column_group`} />
            <input type="hidden" name="formToken" value={window.csrfToken} />
          </form>
        </Drawer.Body>
        <Drawer.Footer>
          <HStack className="acms-admin-width-max">
            <button
              type="button"
              className="acms-admin-btn-admin"
              onClick={handleOpenCustomColumnModal}
              style={{ marginRight: 'auto' }}
            >
              <Icon name="settings" />
              {ACMS.i18n('dataview.column_visibility.edit')}
            </button>
            <button type="button" className="acms-admin-btn-admin" onClick={handleClose}>
              {ACMS.i18n('dataview.column_visibility.cancel')}
            </button>
            <button
              type="submit"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary"
              form={`${id}-column-settings-form`}
            >
              {ACMS.i18n('dataview.column_visibility.save')}
            </button>
          </HStack>
        </Drawer.Footer>
        {mutationStatus === 'pending' && <Splash message={ACMS.i18n('dataview.column_visibility.pending')} />}
      </Drawer>
      <CustomColumnModal
        {...customColumnModalProps}
        isOpen={isCustomColumnModalOpen}
        onClose={handleCloseCustomColumnModal}
      />
    </>
  );
};

export default ColumnConfigDrawer;
