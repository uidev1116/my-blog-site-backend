/* eslint @typescript-eslint/no-explicit-any: 0 */

import type { CellContext, Table } from '@tanstack/react-table';
import DataviewActions from './components/dataview-actions/dataview-actions';
import EmptyState from './components/empty-state/empty-state';
import TableLoader from './components/loaders/table-loader';
import Link from './components/ui/link';
import Button from './components/ui/button';
import Spinner from './components/ui/spinner';
import DataviewBulkAction from './components/dataview-bulk-action/dataview-bulk-action';
import BulkActions from './components/bulk-actions/bulk-actions';
import DataviewMenu from './components/dataview-menu/dataview-menu';
import DataviewPagination from './components/dataview-pagination/dataview-pagination';
import MenuList from './components/menu-list/menu-list';

export type RowData = unknown | object | any[];

export type Operator = 'eq' | 'neq' | 'lt' | 'lte' | 'gt' | 'gte' | 'lk' | 'nlk' | 're' | 'nre' | 'em' | 'nem';

export type AccessorColumnType =
  | 'text'
  | 'number'
  | 'textarea'
  | 'select'
  | 'checkbox'
  | 'radio'
  | 'datetime'
  | 'image'
  | 'file'
  | 'media'
  | 'rich-editor'
  | 'block-editor'
  | 'group'
  | 'array'
  | 'object';

export type DisplayColumnType = 'display';

export type ColumnType = AccessorColumnType | DisplayColumnType;

export type ColumnAccessor<T> = Exclude<keyof T, number | symbol>;

export interface ColumnClassNames {
  header?: string;
  cell?: string;
}

export interface ColumnStyles {
  header?: React.CSSProperties;
  cell?: React.CSSProperties;
}

export interface ColumnExtensions {
  /**
   * The classnames of the column.
   */
  classNames?: ColumnClassNames;

  /**
   * The styles of the column.
   */
  styles?: ColumnStyles;

  /**
   * The sortable of the column.
   */
  enableSorting?: boolean;

  /**
   * The hiding of the column.
   */
  enableHiding?: boolean;

  /**
   * The global filter of the column.
   */
  enableGlobalFilter?: boolean;

  /**
   * The desired size for the column.
   */
  size?: number;

  /**
   * The minimum allowed size for the column
   */
  minSize?: number;

  /**
   * The maximum allowed size for the column
   */
  maxSize?: number;
}

export interface ColumnInterface<T extends RowData> extends ColumnExtensions {
  /**
   * The unique identifier of the column.
   */
  id: string;

  /**
   * The label of the column.
   */
  label: string;

  /**
   * The type of the column.
   */
  type: ColumnType;

  /**
   * The cell getter of the column.
   */
  getValue?: (info: CellContext<T, any>) => React.ReactNode;
}

export interface AccessorColumn<T extends RowData> extends ColumnInterface<T> {
  /**
   * The accessor of the column.
   */
  accessorKey?: string & ColumnAccessor<T>;

  /**
   * The type of the column.
   */
  type: AccessorColumnType;
}

export interface CustomAccessorColumn<T> extends AccessorColumn<T> {
  /**
   * Whether the column is a custom column.
   */
  isCustom: true;
}

export interface DisplayColumn<T> extends ColumnInterface<T> {
  /**
   * The type of the column.
   */
  type: DisplayColumnType;
}

export type Column<T extends RowData> = AccessorColumn<T> | CustomAccessorColumn<T> | DisplayColumn<T>;

export function isAccessorColumn<T extends RowData>(
  column: Column<T> | AccessorColumn<T>
): column is AccessorColumn<T> {
  return column.type !== 'display';
}

export function isDisplayColumn<T extends RowData>(column: Column<T>): column is DisplayColumn<T> {
  return column.type === 'display';
}

export function isCustomAccessorColumn<T extends RowData>(column: Column<T>): column is CustomAccessorColumn<T> {
  return 'isCustom' in column && column.isCustom === true;
}

export type SortDirection = 'asc' | 'desc';

interface ViewInterface {
  /**
   * The layout of the view.
   */
  type: string;

  /**
   * The global search term.
   */
  search?: string;

  /**
   * The sorting configuration.
   */
  sort?: {
    /**
     * The field to sort by.
     */
    id: string;

    /**
     * The direction to sort by.
     */
    direction: SortDirection;
  };

  /**
   * The active page
   */
  pageIndex?: number;

  /**
   * The number of items per page
   */
  pageSize: number;

  /**
   * The visibility of the columns.
   */
  visibility?: ColumnVisibility;

  /**
   * The order of the columns.
   */
  order?: string[];
}

export interface TableView extends ViewInterface {
  type: 'table';
}

export type View = TableView;

interface ActionInterface<T> {
  /**
   * The unique identifier of the action.
   */
  id: string;

  /**
   * The label of the action.
   * In case we want to adjust the label based on the selected items,
   * a function can be provided.
   */
  label: React.ReactNode | ((data: T) => React.ReactNode);

  /**
   * The condition to enable the action.
   */
  condition?: (data: T) => boolean;

  /**
   * The type of the action.
   */
  type?: 'primary' | 'secondary' | 'tertiary';
}

export interface RenderModalProps<T> {
  data: T;
  isOpen: boolean;
  close: () => void;
}

export interface ModalAction<T, U extends React.ElementType = 'button'> extends ActionInterface<T> {
  /**
   * Modal to render when the action is triggered.
   */
  renderModal: (props: RenderModalProps<T>) => React.ReactNode;

  /**
   * The props to pass to the button.
   */
  buttonProps?:
    | React.ComponentPropsWithoutRef<typeof Button<U>>
    | ((data: T) => React.ComponentPropsWithoutRef<typeof Button<U>>);
}

export interface ButtonAction<T, U extends React.ElementType = 'button'> extends ActionInterface<T> {
  /**
   * The callback to execute when the action is triggered.
   */
  onAction?: (data: T) => void;

  /**
   * The props to pass to the button.
   */
  buttonProps?:
    | React.ComponentPropsWithoutRef<typeof Button<U>>
    | ((data: T) => React.ComponentPropsWithoutRef<typeof Button<U>>);
}

export interface LinkAction<T, U extends React.ElementType = 'a'> extends ActionInterface<T> {
  /**
   * The href to navigate when the action is triggered.
   */
  getHref: (data: T) => string;

  /**
   * The props to pass to the link.
   */
  linkProps?:
    | React.ComponentPropsWithoutRef<typeof Link<U>>
    | ((data: T) => React.ComponentPropsWithoutRef<typeof Link<U>>);
}

export type Action<T> = ModalAction<T> | ButtonAction<T> | LinkAction<T>;

export interface BulkAction<T> {
  /**
   * The unique identifier of the action.
   */
  id: string;

  /**
   * The label of the action.
   * In case we want to adjust the label based on the selected items,
   * a function can be provided.
   */
  label: React.ReactNode | ((data: T[]) => React.ReactNode);

  /**
   * The callback to execute when the action is triggered.
   */
  onAction?: (formData: FormData, data: T[]) => Promise<void>;

  /**
   * The form to render when the bulk action is selected.
   */
  renderForm?: (data: T[]) => React.ReactNode;

  /**
   * The condition to enable the bulk action.
   */
  condition?: (data: T[]) => boolean;
}

interface MenuInterface<T> {
  /**
   * The unique identifier of the menu.
   */
  id: string;

  /**
   * The label of the menu.
   * In case we want to adjust the label based on the selected items,
   * a function can be provided.
   */
  label: React.ReactNode | ((data: T[]) => React.ReactNode);

  /**
   * The condition to enable the menu.
   */
  condition?: (data: T[]) => boolean;
}

export interface DropdownMenuGroup<T> {
  /**
   * The unique identifier of the action group.
   */
  id: string;

  /**
   * The title of the action group.
   * In case we want to adjust the title based on the selected items,
   * a function can be provided.
   */
  title: string | ((data: T[]) => string);

  /**
   * The menus to render in the group.
   */
  menus: Exclude<Menu<T>, DropdownMenu<T>>[];

  /**
   * The condition to enable the group.
   */
  condition?: (data: T[]) => boolean;
}

export function isDropdownMenuGroup<T>(menu: unknown): menu is DropdownMenuGroup<T> {
  return typeof menu === 'object' && menu !== null && 'menus' in menu && 'title' in menu;
}

export interface DropdownMenu<T> extends MenuInterface<T> {
  /**
   * The props to pass to the menu button.
   */
  buttonProps?:
    | React.ButtonHTMLAttributes<HTMLButtonElement>
    | ((data: T[]) => React.ButtonHTMLAttributes<HTMLButtonElement>);

  /**
   * The menus to render in the dropdown.
   */
  menus: Exclude<Menu<T>, DropdownMenu<T>>[] | DropdownMenuGroup<T>[];
}

export function isDropdownMenu<T>(menu: unknown): menu is DropdownMenu<T> {
  return typeof menu === 'object' && menu !== null && 'menus' in menu && !isDropdownMenuGroup<T>(menu);
}

export interface ButtonMenu<T, U extends React.ElementType = 'button'> extends MenuInterface<T> {
  /**
   * The callback to execute when the action is triggered.
   */
  onAction?: (data: T[]) => void;

  /**
   * The props to pass to the button.
   */
  buttonProps?:
    | React.ComponentPropsWithoutRef<typeof Button<U>>
    | ((data: T[]) => React.ComponentPropsWithoutRef<typeof Button<U>>);
}

export function isButtonMenu<T>(menu: unknown): menu is ButtonMenu<T> {
  return typeof menu === 'object' && menu !== null && 'onAction' in menu;
}

export interface LinkMenu<T, U extends React.ElementType = 'a'> extends MenuInterface<T> {
  /**
   * The href to navigate when the action is triggered.
   */
  getHref: (data: T[]) => string;

  /**
   * The props to pass to the link.
   */
  linkProps?:
    | React.ComponentPropsWithoutRef<typeof Link<U>>
    | ((data: T[]) => React.ComponentPropsWithoutRef<typeof Link<U>>);
}

export function isLinkMenu<T>(menu: unknown): menu is LinkMenu<T> {
  return typeof menu === 'object' && menu !== null && 'getHref' in menu;
}

export interface RenderDisclosureProps<T> {
  data: T[];
  isOpen: boolean;
  close: () => void;
}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface RenderMenuModalProps<T> extends RenderDisclosureProps<T> {}

export interface ModalMenu<T, U extends React.ElementType = 'button'> extends MenuInterface<T> {
  /**
   * Modal to render when the menu action is triggered.
   */
  renderModal: (props: RenderMenuModalProps<T>) => React.ReactNode;

  /**
   * The props to pass to the button.
   */
  buttonProps?:
    | React.ComponentPropsWithoutRef<typeof Button<U>>
    | ((data: T[]) => React.ComponentPropsWithoutRef<typeof Button<U>>);
}

export function isModalMenu<T>(menu: unknown): menu is ModalMenu<T> {
  return typeof menu === 'object' && menu !== null && 'renderModal' in menu;
}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface RenderMenuDrawerProps<T> extends RenderDisclosureProps<T> {}

export interface DrawerMenu<T, U extends React.ElementType = 'button'> extends MenuInterface<T> {
  /**
   * Drawer to render when the menu action is triggered.
   */
  renderDrawer: (props: RenderMenuDrawerProps<T>) => React.ReactNode;

  /**
   * The props to pass to the button.
   */
  buttonProps?:
    | React.ComponentPropsWithoutRef<typeof Button<U>>
    | ((data: T[]) => React.ComponentPropsWithoutRef<typeof Button<U>>);
}

export function isDrawerMenu<T>(menu: unknown): menu is DrawerMenu<T> {
  return typeof menu === 'object' && menu !== null && 'renderDrawer' in menu;
}

export interface RenderCustomMenuProps<T> {
  data: T[];
}

export interface CustomMenu<T> extends MenuInterface<T> {
  /**
   * The props to pass to the custom menu.
   */
  renderCustomMenu: (props: RenderCustomMenuProps<T>) => React.ReactNode;
}

export function isCustomMenu<T>(menu: unknown): menu is CustomMenu<T> {
  return typeof menu === 'object' && menu !== null && 'renderCustomMenu' in menu;
}

export type Menu<T> = DropdownMenu<T> | ButtonMenu<T> | LinkMenu<T> | ModalMenu<T> | DrawerMenu<T> | CustomMenu<T>;

export function isMenu<T>(menu: unknown): menu is Menu<T> {
  return (
    isButtonMenu<T>(menu) ||
    isLinkMenu<T>(menu) ||
    isModalMenu<T>(menu) ||
    isDrawerMenu<T>(menu) ||
    isCustomMenu<T>(menu)
  );
}

export interface DataviewComponents<T> {
  DataviewActions: React.ComponentType<React.ComponentProps<typeof DataviewActions<T>>>;
  HeaderContents: React.ComponentType<{ data: T[]; columns: Column<T>[]; table: Table<T> }>;
  EmptyState: React.ComponentType<React.ComponentProps<typeof EmptyState>>;
  TableLoader: React.ComponentType<React.ComponentProps<typeof TableLoader>>;
  Spinner: React.ComponentType<React.ComponentProps<typeof Spinner>>;
  DataviewBulkAction: React.ComponentType<React.ComponentProps<typeof DataviewBulkAction<T>>>;
  DataviewPagination: React.ComponentType<React.ComponentProps<typeof DataviewPagination<T>>>;
  BulkActions: React.ComponentType<React.ComponentProps<typeof BulkActions<T>>>;
  DataviewMenu: React.ComponentType<React.ComponentProps<typeof DataviewMenu<T>>>;
  MenuList: React.ComponentType<React.ComponentProps<typeof MenuList<T>>>;
}

export type ColumnVisibility = Record<string, boolean>;

export type ColumnOrder = string[];

export type ColumnConfig = {
  visibility: ColumnVisibility;
  order: ColumnOrder;
};

export type CustomColumnMutationErrors = Record<string, Record<string, boolean>>;

interface CustomColumnMutationSuccessResult<T extends RowData> {
  data: CustomAccessorColumn<T>[];
}

interface CustomColumnMutationErrorResult {
  errors: CustomColumnMutationErrors;
}

export type CustomColumnMutationResult<T extends RowData> =
  | CustomColumnMutationSuccessResult<T>
  | CustomColumnMutationErrorResult;

export type ColumnConfigMutationErrors = Record<string, Record<string, boolean>>;

interface ColumnConfigMutationSuccessResult {
  data: ColumnConfig | null;
}

interface ColumnConfigMutationErrorResult {
  errors: ColumnConfigMutationErrors;
}

export type ColumnConfigMutationResult = ColumnConfigMutationSuccessResult | ColumnConfigMutationErrorResult;

export interface ColumnServiceInterface<T extends RowData> {
  id: string;
  columns: Column<T>[];
  config: ColumnConfig;
  mutateCustomColumns: (formData: FormData) => Promise<CustomColumnMutationResult<T>>;
  invalidateCustomColumns: () => Promise<void>;
  mutateConfig: (formData: FormData) => Promise<ColumnConfigMutationResult>;
  invalidateColumnConfig: () => Promise<void>;
}

export interface Filter {
  /**
   * The field to filter by.
   */
  field: string;

  /**
   * The operator to use.
   */
  operator: Operator;

  /**
   * The value to filter by.
   */
  value: any; // eslint-disable-line @typescript-eslint/no-explicit-any
}

export type OptionData<T extends RowData> = {
  [key in Column<T>['id']]: { value: string; label: string }[];
};

export interface PaginationInfo {
  /**
   * The total number of items.
   */
  totalItems: number;

  /**
   * The page size options.
   */
  pageSizes: number[];
}

export type CreateGetValues<T extends RowData> = (options?: CreateGetValuesOptions) => GetValues<T>;

export type GetGetValue<T extends RowData> = (
  createGetValues: CreateGetValues<T>
) => NonNullable<Column<T>['getValue']>;

export type GetValues<T extends RowData> = Record<ColumnType, Column<T>['getValue']>;

export interface CreateGetValuesOptions {
  formatText?: (value: string) => string;
  formatTextarea?: (value: string) => string;
  formatNumber?: (value: number) => string;
  formatDate?: (value: string) => string;
  formatFileName?: (value: string) => string;
}
