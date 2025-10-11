import { useState, useMemo, useCallback, createContext, useContext } from 'react';
import {
  useReactTable,
  createColumnHelper,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type Row,
  type PaginationState,
  type CellContext,
  type OnChangeFn,
  type VisibilityState,
  type ColumnOrderState,
  type SortingState,
  getFilteredRowModel,
  type SortDirection,
  type RowData,
} from '@tanstack/react-table';
// needed for table body level scope DnD setup
import {
  DndContext,
  closestCenter,
  type DragEndEvent,
  type UniqueIdentifier,
  useSensors,
  PointerSensor,
  KeyboardSensor,
  useSensor,
} from '@dnd-kit/core';
import { restrictToParentElement, restrictToVerticalAxis } from '@dnd-kit/modifiers';
import {
  arrayMove,
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
// needed for row & cell level scope DnD setup
import { CSS } from '@dnd-kit/utilities';
import classnames from 'classnames';
import dayjs from 'dayjs';
import { Grid, GridItem } from '@components/grid';
import Checkbox from './components/checkbox/checkbox';
import RowActions from './components/row-actions/row-actions';
import { components as defaultComponents } from './components';
import {
  isDisplayColumn,
  type Action,
  type BulkAction,
  type Column,
  type DataviewComponents,
  type Menu,
  type PaginationInfo,
  type View,
  type ColumnClassNames,
  type ColumnType,
  type GetGetValue,
  type GetValues,
  type CreateGetValuesOptions,
  type CreateGetValues,
  type ColumnStyles,
  isAccessorColumn,
} from './types';
import DraggableButton from '../draggable-button/draggable-button';
import { Pagination, PaginationRoot, PaginationSummary } from '../pagination/pagination';
import ConditionalWrap from '../conditional-wrap/conditional-wrap';
import { stripHtmlTags, truncate } from '../../utils/string';
import { MediaType } from '../../features/media/types';
import { formatNumber } from '../../utils/number';
import { Tooltip } from '../tooltip';
import { getExt } from '../../utils';
import { getCommonPinningStyles } from './utils';
import VisuallyHidden from '../visually-hidden';

declare module '@tanstack/react-table' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData extends RowData, TValue> {
    type: ColumnType;
    classNames?: ColumnClassNames;
    styles?: ColumnStyles;
    column?: Column<TData>;
  }
}

interface WithId {
  id: number | string;
}

export type DataviewProps<T> = {
  view: View;
  data: T[];
  columns: Column<T>[];
  actions?: Action<T>[];
  bulkActions?: BulkAction<T>[];
  menus?: Menu<T>[];
  onViewChange?: (view: View) => void;
  enableRowSelection?: boolean;
  enableRowDnD?: boolean;
  enablePagination?: boolean;
  enableGlobalFilter?: boolean;
  onDragEnd?: (data: T[], prevData: T[]) => void;
  paginationInfo: PaginationInfo;
  isLoading?: boolean;
  components?: Partial<DataviewComponents<T>>;
  className?: string;
  headerClassName?: string;
  bodyClassName?: string;
  footerClassName?: string;
  getGetValue?: GetGetValue<T>;
  'aria-label'?: string;
  'aria-labelledby'?: string;
  debug?: boolean;
} & (T extends WithId ? { getRowId?: (data: T) => string } : { getRowId: (data: T) => string });

const defaultGetRowId = (data: WithId) => (typeof data.id === 'string' ? data.id : data.id.toString());

const defaultCreateGetValuesOptions = {
  formatText: stripHtmlTags,
  formatTextarea: (value: string) => truncate(stripHtmlTags(value), 100, '...'),
  formatNumber: (value: number) => formatNumber(value),
  formatDate: (value: string) => dayjs(value).format('YYYY/MM/DD HH:mm'),
  formatFileName: (value: string) => truncate(value, 30, '...'),
} as const satisfies CreateGetValuesOptions;

interface MediaBase {
  id: number;
  type: MediaType;
  path: string;
  thumbnail: string;
  filename: string;
  extension: string;
  filesize: number;
  caption: string;
  link: string;
  alt: string;
  text: string;
}

interface ImageMedia extends MediaBase {
  type: 'image';
  focalX?: number;
  focalY?: number;
  width?: number;
  height?: number;
  ratio?: number;
}

interface SvgMedia extends MediaBase {
  type: 'svg';
}

interface FileMedia extends MediaBase {
  type: 'file';
  page: number;
}

type Media = ImageMedia | SvgMedia | FileMedia;

function createGetValues<T extends RowData>(options: CreateGetValuesOptions = {}): GetValues<T> {
  const { formatText, formatTextarea, formatNumber, formatDate, formatFileName }: Required<CreateGetValuesOptions> = {
    ...defaultCreateGetValuesOptions,
    ...options,
  };
  return {
    text: (info) => {
      return formatText(info.getValue());
    },
    number: (info) => {
      const value = parseFloat(info.getValue());
      if (isNaN(value)) {
        return 0;
      }
      return formatNumber(value);
    },
    textarea: (info) => {
      return formatTextarea(info.getValue());
    },
    select: (info) => {
      const column = info.column.columnDef.meta?.column;
      if (!column) {
        throw new Error('column is not found');
      }
      if (!isAccessorColumn(column)) {
        throw new Error('column is not accessor column');
      }
      if (!column.accessorKey) {
        throw new Error('column accessor key is not found');
      }
      const name = `${column.accessorKey}[]`;
      const data = info.row.original as Record<string, unknown>;
      const values = data[name] as string[];
      if (Array.isArray(values)) {
        return (
          <ul className="acms-admin-list-nostyle">
            {values.map((value, index) => (
              // eslint-disable-next-line react/no-array-index-key
              <li key={`${value}-${index}`}>{value}</li>
            ))}
          </ul>
        );
      }
      return info.getValue();
    },
    checkbox: (info) => {
      const column = info.column.columnDef.meta?.column;
      if (!column) {
        throw new Error('column is not found');
      }
      if (!isAccessorColumn(column)) {
        throw new Error('column is not accessor column');
      }
      if (!column.accessorKey) {
        throw new Error('column accessor key is not found');
      }
      const name = `${column.accessorKey}[]`;
      const data = info.row.original as Record<string, unknown>;
      const values = data[name] as string[];
      if (Array.isArray(values)) {
        return (
          <ul className="acms-admin-list-nostyle">
            {values.map((value, index) => (
              // eslint-disable-next-line react/no-array-index-key
              <li key={`${value}-${index}`}>{value}</li>
            ))}
          </ul>
        );
      }
      return info.getValue();
    },
    radio: (info) => {
      return info.getValue();
    },
    datetime: (info) => {
      return formatDate(info.getValue());
    },
    image: (info) => {
      const column = info.column.columnDef.meta?.column;
      if (!column) {
        throw new Error('column is not found');
      }
      if (!isAccessorColumn(column)) {
        throw new Error('column is not accessor column');
      }
      if (!column.accessorKey) {
        throw new Error('column accessor key is not found');
      }
      const name = column.accessorKey.slice(0, column.accessorKey.lastIndexOf('@path'));
      const data = info.row.original as Record<string, unknown>;
      const path = data[`${name}@path`] as string;
      const alt = (data[`${name}@alt`] as string) || '';
      return (
        <div className="acms-admin-cell-image">
          <img src={`${ACMS.Config.ARCHIVES_DIR}${path}`} alt={alt} />
        </div>
      );
    },
    file: (info) => {
      const column = info.column.columnDef.meta?.column;
      if (!column) {
        throw new Error('column is not found');
      }
      if (!isAccessorColumn(column)) {
        throw new Error('column is not accessor column');
      }
      if (!column.accessorKey) {
        throw new Error('column accessor key is not found');
      }
      const name = column.accessorKey.slice(0, column.accessorKey.lastIndexOf('@path'));
      const data = info.row.original as Record<string, unknown>;
      const path = data[`${name}@path`] as string;
      const filename = (data[`${name}@baseName`] as string) || '';
      const extension = getExt(filename);
      const iconPath = ACMS.Library.fileiconPath(extension);
      return (
        <>
          <a href={path} className="acms-admin-cell-file" target="_blank" rel="noreferrer">
            <img src={iconPath} alt="" />
            <span
              data-tooltip-id={`${info.cell.id}-filename-tooptip`}
              data-tooltip-content={filename}
              data-tooltip-place="top-end"
            >
              {formatFileName(filename)}
            </span>
          </a>
          <Tooltip id={`${info.cell.id}-filename-tooptip`} />
        </>
      );
    },
    media: (info: CellContext<T, Record<string, unknown>>) => {
      const column = info.column.columnDef.meta?.column;
      if (!column) {
        throw new Error('column is not found');
      }
      if (!isAccessorColumn(column)) {
        throw new Error('column is not accessor column');
      }
      if (!column.accessorKey) {
        throw new Error('column accessor key is not found');
      }
      const name = column.accessorKey;
      const data = info.row.original as Record<string, unknown>;
      const base: MediaBase = {
        id: parseInt(data[name] as string, 10),
        type: data[`${name}@type`] as Media['type'],
        path: data[`${name}@path`] as string,
        thumbnail: data[`${name}@thumbnail`] as string,
        filename: data[`${name}@name`] as string,
        extension: data[`${name}@extension`] as string,
        filesize: parseInt(data[`${name}@filesize`] as string, 10) || 0,
        caption: (data[`${name}@caption`] as string) || '',
        link: (data[`${name}@link`] as string) || '',
        alt: (data[`${name}@alt`] as string) || '',
        text: (data[`${name}@text`] as string) || '',
      };
      const media: Media = {
        ...base,
        ...(base.type === 'image'
          ? {
              focalX: Object.hasOwn(data, `${name}@focalX`) ? parseFloat(data[`${name}@focalX`] as string) : undefined,
              focalY: Object.hasOwn(data, `${name}@focalY`) ? parseFloat(data[`${name}@focalY`] as string) : undefined,
              width: parseInt(data[`${name}@width`] as string, 10) || 0,
              height: parseInt(data[`${name}@height`] as string, 10) || 0,
              ratio: parseFloat(data[`${name}@ratio`] as string) || 0,
            }
          : {}),
        ...(base.type === 'file'
          ? {
              page: parseInt(data[`${name}@page`] as string, 10) || 1,
            }
          : {}),
      } as Media;

      return {
        image: (
          <div className="acms-admin-cell-image">
            <img src={media.thumbnail} alt={media.alt} />
          </div>
        ),
        svg: (
          <div className="acms-admin-cell-image">
            <img src={media.thumbnail} alt={media.alt} />
          </div>
        ),
        file: (
          <>
            <a href={media.path} className="acms-admin-cell-file" target="_blank" rel="noreferrer">
              <img src={media.thumbnail} alt="" />
              <span
                data-tooltip-id={`${info.cell.id}-${media.id}-media-filename-tooptip`}
                data-tooltip-content={media.filename}
                data-tooltip-place="top-end"
              >
                {formatFileName(media.filename)}
              </span>
            </a>
            <Tooltip id={`${info.cell.id}-${media.id}-media-filename-tooptip`} />
          </>
        ),
      }[media.type];
    },
    'rich-editor': (info) => {
      const { html = '' } = JSON.parse(info.getValue() as string) as { html: string };
      return formatTextarea(html);
    },
    'block-editor': (info) => {
      return formatTextarea(info.getValue());
    },
    group: (info: CellContext<T, unknown[]>) => {
      return ACMS.i18n('dataview.group_cell_value', { length: info.getValue().length });
    },
    array: (info: CellContext<T, unknown[]>) => {
      const values = info.getValue();
      return (
        <ul className="acms-admin-list-nostyle">
          {values.map((value, index) => (
            // eslint-disable-next-line react/no-array-index-key
            <li key={`${value}-${index}`}>{value as React.ReactNode}</li>
          ))}
        </ul>
      );
    },
    object: (info: CellContext<T, Record<string, unknown>>) => {
      return JSON.stringify(info.getValue());
    },
    display: (info) => {
      return info.getValue();
    },
  };
}

function defaultGetGetValue<T extends RowData>(createGetValues: CreateGetValues<T>) {
  return (info: CellContext<T, unknown>) => {
    const getValues = createGetValues();
    const getValue = getValues[info.column.columnDef.meta?.type as ColumnType] as
      | ((info: CellContext<T, unknown>) => React.ReactNode)
      | undefined;
    return typeof getValue === 'function' ? getValue(info) : (info.getValue() as React.ReactNode);
  };
}

interface SortableRowContextValue extends ReturnType<typeof useSortable> {
  id: UniqueIdentifier;
}

const SortableRowContext = createContext<SortableRowContextValue | undefined>(undefined);

const useSortableRow = () => {
  const context = useContext(SortableRowContext);
  if (!context) {
    throw new Error('useSortableRow must be used within a SortableRowContext');
  }
  return context;
};

const RowDragHandleCell = () => {
  const { setActivatorNodeRef, listeners, attributes } = useSortableRow();
  return (
    // Alternatively, you could set these attributes on the rows themselves
    <DraggableButton ref={setActivatorNodeRef} {...listeners} {...attributes} />
  );
};

// Row Component
const SortableRow = <T,>({ row }: { row: Row<T> }) => {
  const { transform, transition, setNodeRef, isDragging, ...rest } = useSortable({
    id: row.id,
  });

  const style: React.CSSProperties = {
    transform: transform ? CSS.Transform.toString({ ...transform, scaleX: 1, scaleY: 1 }) : undefined,
    transition,
    opacity: isDragging ? 0.8 : 1,
    position: 'relative',
  };

  const value = useMemo<SortableRowContextValue>(
    () => ({ id: row.id, transform, transition, setNodeRef, isDragging, ...rest }),
    [row.id, transform, transition, setNodeRef, isDragging, rest]
  );

  return (
    <SortableRowContext.Provider value={value}>
      {/* connect row ref to dnd-kit, apply important styles */}
      <tr
        ref={setNodeRef}
        data-row-id={row.id}
        className={classnames({ 'acms-admin-dragging': isDragging })}
        style={style}
      >
        {row.getVisibleCells().map((cell) => (
          <td
            key={cell.id}
            data-column-id={cell.column.id}
            data-cell-id={cell.id}
            className={cell.column.columnDef.meta?.classNames?.cell}
            style={{ ...getCommonPinningStyles(cell.column), ...cell.column.columnDef.meta?.styles?.cell }}
          >
            {flexRender(cell.column.columnDef.cell, cell.getContext())}
          </td>
        ))}
      </tr>
    </SortableRowContext.Provider>
  );
};

const DataView = <T,>({
  view,
  data,
  columns: columnDefs = [],
  actions = [],
  bulkActions = [],
  menus = [],
  onViewChange,
  enableRowSelection = false,
  enableRowDnD = false,
  enablePagination = false,
  enableGlobalFilter = false,
  getRowId = defaultGetRowId,
  getGetValue = defaultGetGetValue<T>,
  onDragEnd,
  paginationInfo,
  isLoading = false,
  components: componentsProp = {},
  className = '',
  headerClassName = '',
  bodyClassName = '',
  footerClassName = '',
  'aria-label': ariaLabel,
  'aria-labelledby': ariaLabelledBy,
  debug = false,
}: DataviewProps<T>) => {
  const components: DataviewComponents<T> = useMemo(
    () => ({ ...defaultComponents, ...componentsProp }),
    [componentsProp]
  );

  const defaultGetValue = useMemo(() => getGetValue(createGetValues), [getGetValue]);

  const columns: ColumnDef<T>[] = useMemo(() => {
    const columnHelper = createColumnHelper<T>();
    const columns: ColumnDef<T>[] = [
      ...(enableRowDnD
        ? [
            columnHelper.display({
              id: 'drag-handle',
              // eslint-disable-next-line react/no-unstable-nested-components
              cell: () => <RowDragHandleCell />,
              enableGlobalFilter: false,
              enableColumnFilter: false,
              enableHiding: false,
              enableSorting: false,
            }),
          ]
        : []),
      ...(enableRowSelection
        ? [
            columnHelper.display({
              id: 'checkbox',
              // eslint-disable-next-line react/no-unstable-nested-components
              header: ({ table }) => (
                <Checkbox
                  checked={table.getIsAllRowsSelected()}
                  onChange={table.getToggleAllRowsSelectedHandler()}
                  aria-label={
                    table.getIsAllRowsSelected()
                      ? ACMS.i18n('dataview.deselect_all_rows')
                      : ACMS.i18n('dataview.select_all_rows')
                  }
                />
              ),
              // eslint-disable-next-line react/no-unstable-nested-components
              cell: ({ row }) => (
                <Checkbox
                  checked={row.getIsSelected()}
                  aria-label={
                    row.getIsSelected() ? ACMS.i18n('dataview.deselect_rows') : ACMS.i18n('dataview.select_rows')
                  }
                  name="checks[]"
                  value={row.id}
                  onChange={row.getToggleSelectedHandler()}
                  form="bulk-action-form"
                />
              ),
              enableGlobalFilter: false,
              enableColumnFilter: false,
              enableHiding: false,
              enableSorting: false,
              meta: {
                type: 'display',
                classNames: {
                  header: 'acms-admin-table-nowrap',
                },
              },
            }),
          ]
        : []),
      ...columnDefs.map((columnDef) => {
        const { id, label, getValue, type, classNames, styles, ...rest } = columnDef;
        if (!isDisplayColumn(columnDef)) {
          const accessor = (columnDef.accessorKey || id) as Parameters<(typeof columnHelper)['accessor']>[0];
          return columnHelper.accessor(accessor, {
            id,
            header: () => label,
            // eslint-disable-next-line react/no-unstable-nested-components
            cell: (info: CellContext<T, unknown>) => {
              const value = info.getValue();
              if (value === undefined) {
                // 存在しない項目の場合は null を返す
                return null;
              }
              if (value === '' || value === null || (Array.isArray(value) && value.length === 0)) {
                // データが空の場合は '-' を返す
                return <>-</>;
              }
              return getValue?.(info) ?? defaultGetValue(info);
            },
            meta: { type, classNames, styles, column: columnDef },
            ...rest,
          });
        }
        return columnHelper.display({
          id,
          header: () => label,
          cell: (info) => getValue?.(info) ?? defaultGetValue(info),
          meta: { type, classNames, styles, column: columnDef },
          ...rest,
        });
      }),
      ...(actions.length > 0
        ? [
            columnHelper.display({
              id: 'actions',
              header: () => ACMS.i18n('dataview.header.actions'),
              // eslint-disable-next-line react/no-unstable-nested-components
              cell: (info) => <RowActions actions={actions} data={info.row.original} />,
              enableGlobalFilter: false,
              enableColumnFilter: false,
              enableHiding: false,
              enableSorting: false,
            }),
          ]
        : []),
    ];

    return columns;
  }, [columnDefs, enableRowSelection, enableRowDnD, actions, defaultGetValue]);

  const [rowSelection, setRowSelection] = useState({});

  const pagination = useMemo<PaginationState>(() => {
    return {
      pageIndex: (view.pageIndex || 1) - 1, // tanstack-table は 0-indexed なので -1 する
      pageSize: view.pageSize,
    };
  }, [view]);

  const handlePaginationChange = useCallback<OnChangeFn<PaginationState>>(
    (updaterOrValue) => {
      if (onViewChange) {
        const { pageIndex, pageSize } =
          typeof updaterOrValue === 'function' ? updaterOrValue(pagination) : updaterOrValue;
        onViewChange({
          ...view,
          pageIndex: pageIndex + 1, // tanstack-table は 0-indexed なので +1 する
          pageSize,
        });
      }
    },
    [onViewChange, view, pagination]
  );

  const columnVisibility = useMemo<VisibilityState | undefined>(() => {
    return view.visibility;
  }, [view]);

  const columnOrder = useMemo<ColumnOrderState>(() => {
    return ['drag-handle', 'checkbox', ...(view.order || []), 'actions'];
  }, [view.order]);

  const sorting = useMemo<SortingState>(() => {
    return view.sort
      ? [
          {
            id: view.sort.id,
            desc: view.sort.direction === 'desc',
          },
        ]
      : [];
  }, [view.sort]);

  const handleSortingChange = useCallback<OnChangeFn<SortingState>>(
    (updaterOrValue) => {
      if (onViewChange) {
        const newSorting = typeof updaterOrValue === 'function' ? updaterOrValue(sorting) : updaterOrValue;
        onViewChange({
          ...view,
          sort:
            typeof newSorting[0] === 'undefined'
              ? undefined
              : { id: newSorting[0].id, direction: newSorting[0].desc ? 'desc' : 'asc' },
        });
      }
    },
    [onViewChange, view, sorting]
  );

  const globalFilter = useMemo(() => view.search || '', [view.search]);

  const handleGlobalFilterChange = useCallback<OnChangeFn<string>>(
    (updaterOrValue) => {
      if (onViewChange) {
        onViewChange({
          ...view,
          search: typeof updaterOrValue === 'function' ? updaterOrValue(globalFilter) : updaterOrValue,
        });
      }
    },
    [onViewChange, view, globalFilter]
  );

  const table = useReactTable<T>({
    data,
    columns,
    initialState: {
      columnPinning: {
        right: ['actions'],
      },
    },
    state: {
      rowSelection,
      pagination,
      columnVisibility,
      columnOrder,
      sorting,
      globalFilter,
    },
    enableRowSelection,
    enableGlobalFilter,
    // enable row selection for all rows
    onRowSelectionChange: setRowSelection,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getRowId,
    onPaginationChange: handlePaginationChange,
    onSortingChange: handleSortingChange,
    onGlobalFilterChange: handleGlobalFilterChange,
    manualPagination: true,
    manualSorting: true,
    enableSortingRemoval: false,
    debugTable: debug,
  });

  const handlePageChange = useCallback(
    (page: number) => {
      table.setPageIndex(page - 1); // tanstack-table は 0-indexed なので -1 する
    },
    [table]
  );

  const dataIds = useMemo<UniqueIdentifier[]>(() => {
    return data.map(getRowId);
  }, [data, getRowId]);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // reorder rows after drag & drop
  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (active && over && active.id !== over.id) {
        const oldIndex = dataIds.indexOf(active.id);
        const newIndex = dataIds.indexOf(over.id);
        const newData = arrayMove(data, oldIndex, newIndex); // this is just a splice util

        if (onDragEnd) {
          onDragEnd(newData, data);
        }
      }
    },
    [data, dataIds, onDragEnd]
  );

  const renderTable = useCallback(() => {
    if (data.length === 0) {
      return isLoading ? <components.TableLoader /> : <components.EmptyState />;
    }

    return (
      <>
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          modifiers={[restrictToVerticalAxis, restrictToParentElement]}
          onDragEnd={handleDragEnd}
        >
          <div className="acms-admin-table-scroll">
            <table
              className="acms-admin-table-admin acms-admin-table-hover"
              style={{ opacity: isLoading ? 0.5 : 1 }}
              aria-busy={isLoading}
              aria-label={ariaLabel}
              aria-labelledby={ariaLabelledBy}
            >
              <thead className="acms-admin-table-heading">
                {table.getHeaderGroups().map((headerGroup) => (
                  <tr key={headerGroup.id}>
                    {headerGroup.headers.map((header) => (
                      <th
                        key={header.id}
                        data-header-id={header.id}
                        data-column-id={header.column.id}
                        colSpan={header.colSpan}
                        className={header.column.columnDef.meta?.classNames?.header}
                        style={{
                          ...getCommonPinningStyles(header.column),
                          ...header.column.columnDef.meta?.styles?.header,
                        }}
                        aria-sort={
                          header.column.getIsSorted()
                            ? `${header.column.getIsSorted() as SortDirection}ending`
                            : undefined
                        }
                        scope="col"
                      >
                        <ConditionalWrap
                          //  eslint-disable-next-line react/no-unstable-nested-components
                          wrap={(children) => (
                            <button
                              type="button"
                              className="acms-admin-th-sort-btn"
                              onClick={header.column.getToggleSortingHandler()}
                              data-next-sort={
                                header.column.getNextSortingOrder()
                                  ? `${header.column.getNextSortingOrder() as SortDirection}ending`
                                  : 'none'
                              }
                            >
                              {children}
                              <span className="acms-admin-th-sort-btn-icon" />
                              <VisuallyHidden>
                                {ACMS.i18n(`table.label.sort.${header.column.getIsSorted() || 'none'}`)}
                              </VisuallyHidden>
                            </button>
                          )}
                          condition={header.column.getCanSort()}
                        >
                          {header.isPlaceholder
                            ? null
                            : flexRender(header.column.columnDef.header, header.getContext())}
                        </ConditionalWrap>
                      </th>
                    ))}
                  </tr>
                ))}
              </thead>
              <tbody>
                <SortableContext items={dataIds} strategy={verticalListSortingStrategy}>
                  {table.getRowModel().rows.map((row) => (
                    <SortableRow key={row.id} row={row} />
                  ))}
                </SortableContext>
              </tbody>
            </table>
          </div>
        </DndContext>
        {isLoading && data.length > 0 && (
          <div className="acms-admin-position-absolute acms-admin-top-25 acms-admin-left-50 acms-admin-translate-middle">
            <components.Spinner size={24} />
          </div>
        )}
      </>
    );
  }, [ariaLabel, ariaLabelledBy, isLoading, dataIds, table, sensors, handleDragEnd, data, components]);

  const selectedData = table.getSelectedRowModel().rows.map((row) => row.original);

  return (
    <div className={classnames('acms-admin-dataview', className)}>
      <div className={classnames('acms-admin-dataview-header', headerClassName)}>
        {selectedData.length === 0 && <components.HeaderContents data={data} columns={columnDefs} table={table} />}
        <components.DataviewActions
          className="acms-admin-dataview-actions"
          data={data}
          bulkActions={bulkActions}
          menus={menus}
          table={table}
          paginationInfo={paginationInfo}
          enablePagination={enablePagination}
          components={components}
        />
      </div>
      <div className={classnames('acms-admin-dataview-body', bodyClassName)}>{renderTable()}</div>
      <div className={classnames('acms-admin-dataview-footer', footerClassName)}>
        {enablePagination && (
          <Grid>
            <PaginationRoot
              page={table.getState().pagination.pageIndex + 1}
              pageSize={table.getState().pagination.pageSize}
              total={Math.ceil(paginationInfo.totalItems / table.getState().pagination.pageSize)}
              totalItems={paginationInfo.totalItems}
              onChange={handlePageChange}
            >
              <GridItem col={{ md: 6, xs: 12 }}>
                <Pagination aria-label={ACMS.i18n('dataview.pagination.bottom.label')} />
              </GridItem>
              <GridItem col={{ md: 6, xs: 12 }}>
                <PaginationSummary />
              </GridItem>
            </PaginationRoot>
          </Grid>
        )}
      </div>
    </div>
  );
};

export default DataView;
