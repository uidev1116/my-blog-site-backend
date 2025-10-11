import { forwardRef, useCallback, useRef, useState } from 'react';
import {
  DndContext,
  closestCenter,
  DragEndEvent,
  useSensors,
  PointerSensor,
  useSensor,
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

import { v4 as uuidv4 } from 'uuid';
import { Tooltip } from '../../../tooltip';

import Modal from '../../../modal/modal';
import HStack from '../../../stack/h-stack';
import DraggableButton from '../../../draggable-button/draggable-button';
import { CustomAccessorColumn, isCustomAccessorColumn, RowData } from '../../types';
import { CUSTOM_COLUMN_TYPE_OPTIONS } from '../../constants';
import useColumnService from '../../stores/service/hook';
import type { CustomColumnMutationErrors } from '../../types';
import Alert from '../../../alert/alert';
import Splash from '../../../splash/splash';
import VisuallyHidden from '../../../visually-hidden';
import useUpdateEffect from '../../../../hooks/use-update-effect';

const createEmptyColumn = <T extends RowData>(): CustomAccessorColumn<T> => ({
  id: uuidv4(),
  accessorKey: undefined,
  label: '',
  enableSorting: false,
  type: 'text',
  isCustom: true,
});

interface ColumnControlProps<T extends RowData> {
  column: CustomAccessorColumn<T>;
  onDelete?: (id: string) => void;
  sort: number;
}

const ColumnControlWithoutRef = <T extends RowData>(
  { column, onDelete, sort }: ColumnControlProps<T>,
  ref: React.ForwardedRef<HTMLButtonElement>
) => {
  const { attributes, listeners, setNodeRef, transform, transition, setActivatorNodeRef, isDragging } = useSortable({
    id: column.id,
  });

  const handleDelete = useCallback(() => {
    if (onDelete) {
      onDelete(column.id);
    }
  }, [onDelete, column.id]);

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  const { id } = useColumnService();

  return (
    <tr
      ref={setNodeRef}
      style={style}
      className={classnames({
        'acms-admin-dragging': isDragging,
      })}
    >
      <td className="acms-admin-d-none acms-admin-d-md-table-cell acms-admin-table-nowrap">
        <DraggableButton ref={setActivatorNodeRef} {...attributes} {...listeners} />
      </td>
      <td>
        <label
          htmlFor={`input-text-custom_column_label-${column.id}`}
          className="acms-admin-d-block acms-admin-d-md-none"
        >
          {ACMS.i18n('dataview.custom_column.label.label')}
        </label>
        <input
          id={`input-text-custom_column_label-${column.id}`}
          className="acms-admin-form-width-full"
          name={`${id}_custom_column_label[${sort}]`}
          placeholder={ACMS.i18n('dataview.custom_column.label.placeholder')}
          type="text"
          defaultValue={column.label}
        />
      </td>
      <td>
        <label
          htmlFor={`input-text-custom_column_accessor_key-${column.id}`}
          className="acms-admin-d-block acms-admin-d-md-none"
        >
          {ACMS.i18n('dataview.custom_column.accessor_key.label')}
        </label>
        <input
          id={`input-text-custom_column_accessor_key-${column.id}`}
          className="acms-admin-form-width-full"
          placeholder={ACMS.i18n('dataview.custom_column.accessor_key.placeholder')}
          name={`${id}_custom_column_accessor_key[${sort}]`}
          type="text"
          defaultValue={column.accessorKey}
        />
      </td>
      <td>
        <label
          htmlFor={`input-text-custom_column_type-${column.id}`}
          className="acms-admin-d-block acms-admin-d-md-none"
        >
          {ACMS.i18n('dataview.custom_column.type.label')}
        </label>
        <select
          defaultValue={column.type}
          className="acms-admin-form-width-full"
          name={`${id}_custom_column_type[${sort}]`}
          id={`input-text-custom_column_type-${column.id}`}
        >
          {CUSTOM_COLUMN_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </td>
      <td className="acms-admin-table-nowrap">
        <input type="hidden" name={`${id}_custom_column_sort[${sort}]`} value="off" />
        <div className="acms-admin-form-checkbox">
          <label>
            <input
              type="checkbox"
              name={`${id}_custom_column_sort[${sort}]`}
              value="on"
              defaultChecked={column.enableSorting}
            />
            <i className="acms-admin-ico-checkbox" />
            {ACMS.i18n('dataview.custom_column.sort.label')}
          </label>
        </div>
      </td>
      <td className="acms-admin-table-nowrap">
        <HStack spacing="0.25rem" justify="end" display="inline-flex">
          <button
            ref={ref}
            className="acms-admin-btn-admin acms-admin-btn-admin-danger"
            type="button"
            onClick={handleDelete}
          >
            {ACMS.i18n('dataview.custom_column.delete')}
          </button>
        </HStack>
      </td>
    </tr>
  );
};

ColumnControlWithoutRef.displayName = 'ColumnControl';

const ColumnControl = forwardRef(ColumnControlWithoutRef) as <T>(
  props: ColumnControlProps<T> & { ref?: React.ForwardedRef<HTMLButtonElement> }
) => JSX.Element;

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface CustomColumnModalProps extends React.ComponentPropsWithoutRef<typeof Modal> {}

function defaultColumns<T extends RowData>(columns: CustomAccessorColumn<T>[]): CustomAccessorColumn<T>[] {
  return columns.length > 0 ? columns : [createEmptyColumn()];
}

const CustomColumnModal = <T extends RowData>({ onClose, onAfterOpen, ...props }: CustomColumnModalProps) => {
  const {
    id,
    columns: globalColumns,
    mutateCustomColumns,
    invalidateCustomColumns,
    invalidateColumnConfig,
  } = useColumnService();
  const getCustomColumnsFromGlobal = useCallback(
    <T extends RowData>() =>
      defaultColumns<T>(globalColumns.filter(isCustomAccessorColumn) as CustomAccessorColumn<T>[]),
    [globalColumns]
  );
  const [customColumns, setCustomColumns] = useState<CustomAccessorColumn<T>[]>(() => getCustomColumnsFromGlobal());

  useUpdateEffect(() => {
    setCustomColumns(getCustomColumnsFromGlobal());
  }, [getCustomColumnsFromGlobal]);

  const handleAfterOpen = useCallback(async () => {
    // モーダルが開いたときに カスタムカラムの状態を最新の状態に更新する
    await invalidateCustomColumns();
    if (onAfterOpen) {
      onAfterOpen();
    }
  }, [onAfterOpen, invalidateCustomColumns]);

  const addButtonRef = useRef<HTMLButtonElement>(null);
  const deleteButtonRefs = useRef<(HTMLButtonElement | null)[]>([]);

  const setDeleteButtonRef = useCallback((index: number, node: HTMLButtonElement | null) => {
    deleteButtonRefs.current[index] = node;
  }, []);

  const addItem = useCallback(() => {
    const newColumn = createEmptyColumn<T>();
    setCustomColumns((prev) => [...prev, newColumn]);
  }, [setCustomColumns]);

  const deleteItem = useCallback(
    (id: string) => {
      setCustomColumns((prev) => prev.filter((column) => column.id !== id));
      if (deleteButtonRefs.current) {
        const index = customColumns.findIndex((column) => column.id === id);
        const lastIndex = deleteButtonRefs.current.findLastIndex((ref) => ref !== null);
        const focusIndex = index === lastIndex ? index - 1 : index + 1;
        if (focusIndex >= 0) {
          deleteButtonRefs.current[focusIndex]?.focus();
        } else {
          addButtonRef.current?.focus();
        }
      }
    },
    [setCustomColumns, customColumns]
  );

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
      setCustomColumns((prevColumns) => {
        const oldIndex = prevColumns.findIndex((column) => column.id === active.id);
        const newIndex = prevColumns.findIndex((column) => column.id === over.id);

        return arrayMove(prevColumns, oldIndex, newIndex);
      });
    }
  }

  const [mutationStatus, setMutationStatus] = useState<'success' | 'failure' | 'pending' | 'none'>('none');
  const [errors, setErrors] = useState<CustomColumnMutationErrors>({});

  const handleSubmit = useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      if (!(event.target instanceof HTMLFormElement)) {
        return;
      }
      event.preventDefault();
      setMutationStatus('pending');
      const result = await mutateCustomColumns(new FormData(event.target));
      if ('errors' in result) {
        setMutationStatus('failure');
        setErrors(result.errors);
        return;
      }
      setMutationStatus('success');
      await invalidateCustomColumns();
      await invalidateColumnConfig();
    },
    [mutateCustomColumns, invalidateCustomColumns, invalidateColumnConfig]
  );

  const handleAlertClose = useCallback(() => {
    setMutationStatus('none');
  }, []);

  return (
    <Modal
      onClose={handleClose}
      onAfterOpen={handleAfterOpen}
      aria-labelledby="acms-custom-column-modal-title"
      size="large"
      {...props}
    >
      <Modal.Header>{ACMS.i18n('dataview.custom_column.title')}</Modal.Header>
      <Modal.Body>
        {mutationStatus === 'success' && (
          <Alert type="info" icon={<span className="acms-admin-icon-news" />} onClose={handleAlertClose}>
            {ACMS.i18n('dataview.custom_column.success')}
          </Alert>
        )}
        {mutationStatus === 'failure' && (
          <Alert type="danger" icon={<span className="acms-admin-icon-attention" />} onClose={handleAlertClose}>
            <div>
              {ACMS.i18n('dataview.custom_column.failure')}
              <ul>
                {errors?.column_id?.all_unique && (
                  <li>{ACMS.i18n('dataview.custom_column.error.column_id.all_unique')}</li>
                )}
                {errors?.column_id?.required && <li>{ACMS.i18n('dataview.custom_column.error.column_id.required')}</li>}
                {errors?.column_accessor_key?.all_unique && (
                  <li>{ACMS.i18n('dataview.custom_column.error.column_accessor_key.all_unique')}</li>
                )}
                {errors?.column_accessor_key?.required && (
                  <li>{ACMS.i18n('dataview.custom_column.error.column_accessor_key.required')}</li>
                )}
                {errors?.column_label?.required && (
                  <li>{ACMS.i18n('dataview.custom_column.error.column_label.required')}</li>
                )}
                {errors?.column_type?.required && (
                  <li>{ACMS.i18n('dataview.custom_column.error.column_type.required')}</li>
                )}
              </ul>
            </div>
          </Alert>
        )}
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          modifiers={[restrictToVerticalAxis, restrictToParentElement]}
          onDragEnd={handleDragEnd}
        >
          <form id="column-edit-form" method="post" className="acms-admin-form" onSubmit={handleSubmit}>
            <table
              className="acms-admin-table-admin-edit"
              style={{ '--acms-admin-table-admin-edit-border': 'none' } as React.CSSProperties}
            >
              <thead className="acms-admin-d-none acms-admin-d-md-table-header-group">
                <tr>
                  <th className="acms-admin-admin-config-table-item-handle acms-admin-table-nowrap">
                    <VisuallyHidden>{ACMS.i18n('dataview.custom_column.handle.label')}</VisuallyHidden>
                  </th>
                  <th className="acms-admin-table-left">
                    {ACMS.i18n('dataview.custom_column.label.label')}
                    <i
                      className="acms-admin-icon-tooltip"
                      data-tooltip-id="column-label-tooltip"
                      data-tooltip-content={ACMS.i18n('dataview.custom_column.label.tooltip')}
                    />
                    <Tooltip id="column-label-tooltip" />
                  </th>
                  <th className="acms-admin-table-left">
                    {ACMS.i18n('dataview.custom_column.accessor_key.label')}
                    <i
                      className="acms-admin-icon-tooltip"
                      data-tooltip-id="column-accessor-key-tooltip"
                      data-tooltip-content={ACMS.i18n('dataview.custom_column.accessor_key.tooltip')}
                    />
                    <Tooltip id="column-accessor-key-tooltip" />
                  </th>
                  <th className="acms-admin-table-left">
                    {ACMS.i18n('dataview.custom_column.type.label')}
                    <i
                      className="acms-admin-icon-tooltip"
                      data-tooltip-id="column-type-tooltip"
                      data-tooltip-content={ACMS.i18n('dataview.custom_column.type.tooltip')}
                    />
                    <Tooltip id="column-type-tooltip" />
                  </th>
                  <th className="acms-admin-table-left">
                    <VisuallyHidden>{ACMS.i18n('dataview.custom_column.sort.label')}</VisuallyHidden>
                  </th>
                  <th className="acms-admin-admin-config-table-action">
                    <VisuallyHidden>{ACMS.i18n('dataview.custom_column.action')}</VisuallyHidden>
                  </th>
                </tr>
              </thead>
              <tbody>
                <SortableContext
                  disabled={mutationStatus === 'pending'}
                  items={customColumns.map((column) => column.id)}
                  strategy={verticalListSortingStrategy}
                >
                  {customColumns.map((column, index) => (
                    <ColumnControl
                      ref={(ref) => setDeleteButtonRef(index, ref)}
                      key={column.id}
                      column={column}
                      onDelete={deleteItem}
                      sort={index}
                    />
                  ))}
                </SortableContext>
              </tbody>
              <tfoot>
                <tr>
                  <td colSpan={6}>
                    <HStack spacing="0.25rem" justify="end" display="inline-flex">
                      <button ref={addButtonRef} className="acms-admin-btn-admin" type="button" onClick={addItem}>
                        {ACMS.i18n('dataview.custom_column.add')}
                      </button>
                    </HStack>
                  </td>
                </tr>
              </tfoot>
            </table>
            {customColumns.map((column, index) => (
              <input key={column.id} type="hidden" name={`${id}_custom_column_id[${index}]`} defaultValue={column.id} />
            ))}
            <input type="hidden" name={`${id}_custom_column_id:v#all_unique`} />
            <input type="hidden" name={`${id}_custom_column_id:v#required`} />
            <input type="hidden" name={`${id}_custom_column_accessor_key:v#all_unique`} />
            <input type="hidden" name={`${id}_custom_column_accessor_key:v#required`} />
            <input type="hidden" name={`${id}_custom_column_label:v#required`} />
            <input type="hidden" name={`@${id}_custom_column_group[]`} value={`${id}_custom_column_id`} />
            <input type="hidden" name={`@${id}_custom_column_group[]`} value={`${id}_custom_column_accessor_key`} />
            <input type="hidden" name={`@${id}_custom_column_group[]`} value={`${id}_custom_column_label`} />
            <input type="hidden" name={`@${id}_custom_column_group[]`} value={`${id}_custom_column_sort`} />
            <input type="hidden" name={`@${id}_custom_column_group[]`} value={`${id}_custom_column_type`} />
            <input type="hidden" name="config[]" value={`${id}_custom_column_id`} />
            <input type="hidden" name="config[]" value={`${id}_custom_column_accessor_key`} />
            <input type="hidden" name="config[]" value={`${id}_custom_column_label`} />
            <input type="hidden" name="config[]" value={`${id}_custom_column_sort`} />
            <input type="hidden" name="config[]" value={`${id}_custom_column_type`} />
            <input type="hidden" name="config[]" value={`@${id}_custom_column_group`} />
            <input type="hidden" name="formToken" value={window.csrfToken} />
          </form>
        </DndContext>
      </Modal.Body>
      <Modal.Footer>
        <HStack display="inline-flex">
          <button
            type="button"
            className="acms-admin-btn"
            onClick={handleClose}
            disabled={mutationStatus === 'pending'}
          >
            {ACMS.i18n('dataview.custom_column.cancel')}
          </button>
          <button
            type="submit"
            className="acms-admin-btn acms-admin-btn-primary"
            form="column-edit-form"
            disabled={mutationStatus === 'pending'}
          >
            {ACMS.i18n('dataview.custom_column.save')}
          </button>
        </HStack>
      </Modal.Footer>
      {mutationStatus === 'pending' && <Splash message={ACMS.i18n('dataview.custom_column.pending')} />}
    </Modal>
  );
};

export default CustomColumnModal;
