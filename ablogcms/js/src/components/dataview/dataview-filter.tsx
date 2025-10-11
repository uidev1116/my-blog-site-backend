import React, {
  ForwardedRef,
  createContext,
  forwardRef,
  useCallback,
  useContext,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from 'react';
import classnames from 'classnames';
import { v4 as uuidv4 } from 'uuid';
import { flushSync } from 'react-dom';
import { Icon } from '@components/icon';
import DatePicker from '../date-picker/date-picker';
import {
  type Filter,
  type ColumnType,
  type Column,
  type OptionData,
  type RowData,
  type AccessorColumn,
  isCustomAccessorColumn,
  isDisplayColumn,
} from './types';
import HStack from '../stack/h-stack';
import { FilterableColumnTypes } from './constants';
import { getColumnAccessorKey, sortColumnsForFilterSelect } from './utils';
import isDateString from '../../utils/isDateString';
import useUpdateEffect from '../../hooks/use-update-effect';
import VisuallyHidden from '../visually-hidden';

interface IdentifiedFilter extends Filter {
  id: string;
}

const createIdentifiedFilter = (filter: Filter): IdentifiedFilter => ({ ...filter, id: uuidv4() });

// Context to handle state
const FilterContext = createContext<
  | {
      columns: AccessorColumn<RowData>[];
      optionData: OptionData<unknown>;
      filters: IdentifiedFilter[];
      addItem: () => void;
      deleteItem: (id: string) => void;
      setFilterControlRef: (index: number, ref: FilterControlRef | null) => void;
    }
  | undefined
>(undefined);

const useFilterContext = () => {
  const context = useContext(FilterContext);
  if (!context) {
    throw new Error('useFilterContext must be used within a FilterProvider');
  }
  return context;
};

const createFilterOperators = (type: ColumnType) => {
  switch (type) {
    case 'select':
    case 'checkbox':
    case 'radio':
      return [
        { value: 'eq', label: ACMS.i18n('dataview.filter.operator.eq') },
        { value: 'neq', label: ACMS.i18n('dataview.filter.operator.neq') },
      ];
    case 'text':
    case 'textarea':
      return [
        { value: 'eq', label: ACMS.i18n('dataview.filter.operator.eq') },
        { value: 'neq', label: ACMS.i18n('dataview.filter.operator.neq') },
        { value: 'lk', label: ACMS.i18n('dataview.filter.operator.lk') },
        { value: 'nlk', label: ACMS.i18n('dataview.filter.operator.nlk') },
      ];
    case 'number':
      return [
        { value: 'eq', label: ACMS.i18n('dataview.filter.operator.eq') },
        { value: 'neq', label: ACMS.i18n('dataview.filter.operator.neq') },
        { value: 'gt', label: ACMS.i18n('dataview.filter.operator.gt') },
        { value: 'gte', label: ACMS.i18n('dataview.filter.operator.gte') },
        { value: 'lt', label: ACMS.i18n('dataview.filter.operator.lt') },
        { value: 'lte', label: ACMS.i18n('dataview.filter.operator.lte') },
      ];
    case 'datetime':
      return [
        { value: 'eq', label: ACMS.i18n('dataview.filter.operator.eq') },
        { value: 'neq', label: ACMS.i18n('dataview.filter.operator.neq') },
        { value: 'gt', label: ACMS.i18n('dataview.filter.operator.gt') },
        { value: 'gte', label: ACMS.i18n('dataview.filter.operator.gte') },
        { value: 'lt', label: ACMS.i18n('dataview.filter.operator.lt') },
        { value: 'lte', label: ACMS.i18n('dataview.filter.operator.lte') },
      ];
    default:
      return [];
  }
};

function getSelectedColumn<T>(columns: AccessorColumn<T>[], filter: Filter): AccessorColumn<T> | null {
  return columns.find((column) => getColumnAccessorKey(column) === filter.field) || null;
}

interface OperatorSelectProps<T> extends Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'name'> {
  column: AccessorColumn<T> | null;
}

const OperatorSelectWithoutRef = <T,>(
  { column, ...props }: OperatorSelectProps<T>,
  ref: React.ForwardedRef<HTMLSelectElement>
) => {
  if (column === null) {
    return (
      <select ref={ref} {...props}>
        <option value="">{ACMS.i18n('select.not_selected')}</option>
      </select>
    );
  }
  const operators = createFilterOperators(column.type);
  return (
    <select ref={ref} name={`${getColumnAccessorKey(column)}@operator[]`} {...props}>
      {operators.map((operator) => (
        <option key={operator.value} value={operator.value}>
          {operator.label}
        </option>
      ))}
    </select>
  );
};

OperatorSelectWithoutRef.displayName = 'OperatorSelect';
const OperatorSelect = forwardRef(OperatorSelectWithoutRef) as <T>(
  props: OperatorSelectProps<T> & { ref?: React.ForwardedRef<HTMLSelectElement> }
) => JSX.Element;

interface ValueControlProps<T> {
  column: AccessorColumn<T> | null;
  defaultValue: Filter['value'];
  className?: string;
}

const ValueControlWithoutRef = <T,>(
  { className, column, defaultValue }: ValueControlProps<T>,
  ref: React.ForwardedRef<HTMLInputElement | HTMLSelectElement>
) => {
  const { optionData } = useFilterContext();
  if (column === null) {
    return <input ref={ref as ForwardedRef<HTMLInputElement>} className={className} type="text" />;
  }
  const name = `${getColumnAccessorKey(column)}[]`;
  switch (column.type) {
    case 'select':
    case 'checkbox':
    case 'radio':
      const options: { value: string; label: string }[] = optionData[getColumnAccessorKey(column)] || [];
      return (
        <select
          defaultValue={defaultValue}
          ref={ref as ForwardedRef<HTMLSelectElement>}
          className={className}
          name={name}
          key={`${JSON.stringify(options)}-${defaultValue}`}
        >
          {isCustomAccessorColumn(column) && (
            <option value="">{ACMS.i18n('dataview.filter.value_control.not_selected')}</option>
          )}
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      );
    case 'text':
      return (
        <input
          ref={ref as ForwardedRef<HTMLInputElement>}
          className={className}
          type="text"
          name={name}
          defaultValue={defaultValue}
          key={defaultValue}
        />
      );
    case 'number':
      return (
        <input
          ref={ref as ForwardedRef<HTMLInputElement>}
          className={className}
          type="text"
          inputMode="numeric"
          pattern="\d*"
          name={name}
          defaultValue={defaultValue}
          key={defaultValue}
        />
      );
    case 'datetime':
      return (
        <DatePicker
          ref={ref as ForwardedRef<HTMLInputElement>}
          className={className}
          name={name}
          defaultValue={isDateString(defaultValue) ? defaultValue : undefined}
          options={{ enableTime: true, enableSeconds: true, dateFormat: 'Y-m-d H:i:S', time_24hr: true }}
          key={defaultValue}
        />
      );
    default:
      return (
        <input
          ref={ref as ForwardedRef<HTMLInputElement>}
          className={className}
          type="text"
          name={name}
          defaultValue={defaultValue}
          key={defaultValue}
        />
      );
  }
};

ValueControlWithoutRef.displayName = 'ValueControl';

const ValueControl = forwardRef(ValueControlWithoutRef) as <T>(
  props: ValueControlProps<T> & { ref?: React.ForwardedRef<HTMLInputElement | HTMLSelectElement> }
) => JSX.Element;

interface FilterControlProps {
  filter: IdentifiedFilter;
  onDelete?: (id: string) => void;
  onAdd?: () => void;
}

interface FilterControlRef {
  focusAddButton(): void;
  focusDeleteButton(): void;
}

const FilterControlWithoutRef = <T,>(
  { filter, onDelete, onAdd }: FilterControlProps,
  ref: React.ForwardedRef<FilterControlRef>
) => {
  const { columns } = useFilterContext();

  const filterableColumns = useMemo(
    () => columns.filter((column) => FilterableColumnTypes.some((type) => type === column.type)),
    [columns]
  );

  const sortedColumns = useMemo(() => sortColumnsForFilterSelect(filterableColumns), [filterableColumns]);

  const [selectedColumn, setSelectedColumn] = useState<AccessorColumn<T> | null>(null);

  useEffect(() => {
    setSelectedColumn(getSelectedColumn(sortedColumns, filter) as AccessorColumn<T> | null);
  }, [sortedColumns, filter]);

  const handleOptionChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const key = e.target.value;
      const column = filterableColumns.find((column) => getColumnAccessorKey(column) === key) as
        | AccessorColumn<T>
        | undefined;
      setSelectedColumn(column || null);
    },
    [filterableColumns]
  );

  const handleDelete = useCallback(() => {
    if (onDelete) {
      onDelete(filter.id);
    }
  }, [filter.id, onDelete]);

  const handleAdd = useCallback(() => {
    if (onAdd) {
      onAdd();
    }
  }, [onAdd]);

  const addButtonRef = useRef<HTMLButtonElement>(null);
  const deleteButtonRef = useRef<HTMLButtonElement>(null);
  useImperativeHandle(ref, () => {
    return {
      focusAddButton() {
        addButtonRef.current?.focus();
      },
      focusDeleteButton() {
        deleteButtonRef.current?.focus();
      },
    };
  }, []);

  return (
    <div className="acms-admin-dataview-filter-row">
      <div className="acms-admin-dataview-filter-cell">
        <select
          value={selectedColumn ? getColumnAccessorKey(selectedColumn) : undefined}
          className="acms-admin-form-width-full"
          name="field[]"
          onChange={handleOptionChange}
        >
          <option value="">{ACMS.i18n('select.not_selected')}</option>
          {sortedColumns.map((column) => (
            <option key={column.id} value={getColumnAccessorKey(column)}>
              {column.label}
            </option>
          ))}
        </select>
      </div>
      <div className="acms-admin-dataview-filter-cell">
        <OperatorSelect<T>
          column={selectedColumn}
          className="acms-admin-form-width-full"
          defaultValue={filter.operator}
          key={`${selectedColumn?.type}-${filter.operator}`}
        />
      </div>
      <div className="acms-admin-dataview-filter-cell">
        <ValueControl<T>
          column={selectedColumn}
          defaultValue={filter.value}
          key={filter.value}
          className="acms-admin-form-width-full"
        />
      </div>
      <div className="acms-admin-dataview-filter-cell">
        <HStack spacing="0.25rem" justify="end" display="inline-flex">
          <button
            ref={addButtonRef}
            type="button"
            className="acms-admin-btn-icon-unstyled"
            onClick={handleAdd}
            aria-label={ACMS.i18n('dataview.filter.add')}
            style={onAdd ? undefined : { visibility: 'hidden' }}
          >
            <Icon name="add_circle" />
          </button>
          <button
            ref={deleteButtonRef}
            className="acms-admin-btn-icon-unstyled"
            type="button"
            onClick={handleDelete}
            aria-label={ACMS.i18n('dataview.filter.delete')}
            style={onDelete ? undefined : { visibility: 'hidden' }}
          >
            <Icon name="delete" />
          </button>
        </HStack>
      </div>
    </div>
  );
};

FilterControlWithoutRef.displayName = 'FilterControl';

const FilterControl = forwardRef(FilterControlWithoutRef) as <T>( // eslint-disable-line @typescript-eslint/no-unused-vars
  props: FilterControlProps & { ref?: React.ForwardedRef<FilterControlRef> }
) => JSX.Element;

interface FilterProps {
  className?: string;
  style?: React.CSSProperties;
}

const Filter = <T,>({ className, style }: FilterProps) => {
  const { filters, addItem, deleteItem, setFilterControlRef } = useFilterContext();

  const canDelete = useCallback(
    (index: number) => {
      if (index === 0 && filters.length === 1) {
        return false;
      }
      return true;
    },
    [filters]
  );
  return (
    <div className={classnames('acms-admin-dataview-filter', className)} style={style}>
      <div className="acms-admin-dataview-filter-header acms-admin-d-none acms-admin-d-md-block">
        <div className="acms-admin-dataview-filter-row">
          <div className="acms-admin-dataview-filter-cell">{ACMS.i18n('dataview.filter.thead.field')}</div>
          <div className="acms-admin-dataview-filter-cell">{ACMS.i18n('dataview.filter.thead.operator')}</div>
          <div className="acms-admin-dataview-filter-cell">{ACMS.i18n('dataview.filter.thead.value')}</div>
          <div className="acms-admin-dataview-filter-cell">
            <VisuallyHidden>{ACMS.i18n('dataview.filter.thead.action')}</VisuallyHidden>
          </div>
        </div>
      </div>
      <div className="acms-admin-dataview-filter-body">
        {filters.map((filter, index) => {
          return (
            <FilterControl<T>
              ref={(ref) => setFilterControlRef(index, ref)}
              key={filter.id}
              filter={filter}
              onDelete={canDelete(index) ? deleteItem : undefined}
              onAdd={index === filters.length - 1 ? addItem : undefined}
            />
          );
        })}
      </div>
    </div>
  );
};

function createEmptyFilter(): IdentifiedFilter {
  return createIdentifiedFilter({ field: '', operator: 'eq', value: '' });
}

function defaultFilters(filters: Filter[]): IdentifiedFilter[] {
  return filters.length > 0 ? filters.map(createIdentifiedFilter) : [createEmptyFilter()];
}

interface FilterProviderProps<T extends RowData> {
  columns: Column<T>[];
  optionData?: OptionData<T>;
  filterColumns?: (column: Column<T>) => boolean;
  filters?: Filter[];
  children?: React.ReactNode;
}

const FilterProvider = <T extends RowData>({
  columns,
  optionData = {},
  filterColumns = () => true,
  filters: filtersProp = [],
  children,
}: FilterProviderProps<T>) => {
  const [filters, setFilters] = useState<IdentifiedFilter[]>(defaultFilters(filtersProp));

  useUpdateEffect(() => {
    setFilters(defaultFilters(filtersProp));
  }, [filtersProp]);

  const filterControlRefs = useRef<(FilterControlRef | null)[]>([]);

  const setFilterControlRef = useCallback((index: number, ref: FilterControlRef | null) => {
    filterControlRefs.current[index] = ref;
  }, []);

  const addItem = useCallback(() => {
    flushSync(() => {
      const newFilter = createEmptyFilter();
      setFilters((prev) => [...prev, newFilter]);
    });
    // flushSync でDOMの更新を待ってからフォーカスを移動する
    if (filterControlRefs.current) {
      const focasIndex = filterControlRefs.current.findLastIndex((ref) => ref !== null);
      filterControlRefs.current[focasIndex]?.focusAddButton();
    }
  }, [setFilters]);

  const deleteItem = useCallback(
    (id: string) => {
      const index = filters.findIndex((filter) => filter.id === id);
      flushSync(() => {
        setFilters((prev) => prev.filter((filter) => filter.id !== id));
      });
      // flushSync でDOMの更新を待ってからフォーカスを移動する
      if (filterControlRefs.current) {
        const lastIndex = filterControlRefs.current.findLastIndex((ref) => ref !== null);
        // 削除したフィルターが最後のフィルターだった場合、一つ前のフィルターにフォーカスを移動する
        // それ以外の場合、削除したフィルターと同じインデックスのフィルターにフォーカスを移動する
        const focusIndex = index > lastIndex ? index - 1 : index;
        if (focusIndex > 0) {
          filterControlRefs.current[focusIndex]?.focusDeleteButton();
        } else {
          filterControlRefs.current[0]?.focusAddButton();
        }
      }
    },
    [setFilters, filters]
  );

  const filteredColumns = useMemo<AccessorColumn<T>[]>(
    () => columns.filter((column) => !isDisplayColumn(column)).filter(filterColumns) as AccessorColumn<T>[],
    [columns, filterColumns]
  );

  const value = useMemo(
    () => ({
      columns: filteredColumns as AccessorColumn<unknown>[],
      optionData,
      filters,
      addItem,
      deleteItem,
      setFilterControlRef,
    }),
    [filteredColumns, optionData, filters, addItem, deleteItem, setFilterControlRef]
  );

  return <FilterContext.Provider value={value}>{children}</FilterContext.Provider>;
};

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface DataviewFilterProps<T extends RowData> extends FilterProviderProps<T> {}

const DataviewFilter = <T extends RowData>(props: DataviewFilterProps<T>) => {
  return (
    <FilterProvider {...props}>
      <Filter />
    </FilterProvider>
  );
};

export default DataviewFilter;
