import { type CellContext } from '@tanstack/react-table';
import dayjs from 'dayjs';
import { type Column, type ColumnOrder } from '../../../components/dataview/types';
import { type ModuleType } from '../types';

export const MODULE_STATUSES = [
  { value: 'open', label: ACMS.i18n('module.status.open') },
  { value: 'close', label: ACMS.i18n('module.status.close') },
] as const;

export const MODULE_SCOPE = [
  { value: 'local', label: ACMS.i18n('module.scope.local') },
  { value: 'global', label: ACMS.i18n('module.scope.global') },
] as const;

export const MODULE_BLOG_AXIS = [
  { value: 'self', label: ACMS.i18n('module.blog_axis.self') },
  { value: 'descendant-or-self', label: ACMS.i18n('module.blog_axis.descendant-or-self') },
] as const;

export const MODULE_STATEUS_VARIANTS = {
  open: 'info',
  close: 'danger',
} as const;

/**
 * カラムの定義
 */
export const MODULE_COLUMNS = [
  {
    id: 'id',
    label: ACMS.i18n('module_index.id'),
    accessorKey: 'id',
    enableSorting: true,
    type: 'number',
    getValue: (info: CellContext<ModuleType, ModuleType['id']>) => (
      <span className="acms-admin-label">{info.getValue()}</span>
    ),
  },
  {
    id: 'identifier',
    label: ACMS.i18n('module_index.identifier'),
    accessorKey: 'identifier',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['identifier']>) => info.getValue(),
  },
  {
    id: 'status',
    label: ACMS.i18n('module_index.status'),
    accessorKey: 'status',
    enableSorting: true,
    type: 'object',
    getValue: (info: CellContext<ModuleType, ModuleType['status']>) => {
      const status = MODULE_STATUSES.find((status) => status.value === info.getValue());
      if (status === undefined) {
        return <span className="acms-admin-label">{info.getValue()}</span>;
      }
      return (
        <span className={`acms-admin-label acms-admin-label-${MODULE_STATEUS_VARIANTS[status.value]}`}>
          {status.label}
        </span>
      );
    },
  },
  {
    id: 'label',
    label: ACMS.i18n('module_index.label'),
    accessorKey: 'label',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['label']>) => info.getValue(),
  },
  {
    id: 'name',
    label: ACMS.i18n('module_index.name'),
    accessorKey: 'name',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['name']>) => info.getValue(),
  },
  {
    id: 'scope',
    label: ACMS.i18n('module_index.scope'),
    accessorKey: 'scope',
    enableSorting: true,
    type: 'object',
    getValue: (info: CellContext<ModuleType, ModuleType['scope']>) => {
      const { scope: originalScope } = info.row.original;
      const scope = MODULE_SCOPE.find((scope) => scope.value === originalScope);
      if (scope === undefined || scope.value === 'local') {
        return <span className="acms-text-center">-</span>;
      }
      return <span className="acms-admin-label acms-admin-label-info">{scope.label}</span>;
    },
  },
  {
    id: 'created_datetime',
    label: ACMS.i18n('module_index.created_datetime'),
    accessorKey: 'created_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<ModuleType, ModuleType['created_datetime']>) => {
      if (info.getValue() === null) {
        return null;
      }
      return dayjs(info.getValue()).format('YYYY/MM/DD HH:mm');
    },
  },
  {
    id: 'updated_datetime',
    label: ACMS.i18n('module_index.updated_datetime'),
    accessorKey: 'updated_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<ModuleType, ModuleType['updated_datetime']>) => {
      if (info.getValue() === null) {
        return null;
      }
      return dayjs(info.getValue()).format('YYYY/MM/DD HH:mm');
    },
  },
  {
    id: 'blog_id',
    label: ACMS.i18n('module_index.blog'),
    accessorKey: 'blog_id',
    enableSorting: true,
    type: 'object',
    getValue: (info: CellContext<ModuleType, ModuleType['blog_id']>) => {
      const { blog } = info.row.original;
      if (blog === undefined) {
        return (
          <span>
            <i className="acms-admin-icon acms-admin-icon-blog" aria-hidden />
            {ACMS.i18n('module_index.blog_parent')}
          </span>
        );
      }
      return (
        <span>
          <i className="acms-admin-icon acms-admin-icon-blog" aria-hidden />
          {blog.name}
        </span>
      );
    },
  },
  {
    id: 'custom_field',
    label: ACMS.i18n('module_index.custom_field'),
    accessorKey: 'custom_field',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['custom_field']>) =>
      info.getValue()
        ? ACMS.i18n('module_index.custom_field_value.on')
        : ACMS.i18n('module_index.custom_field_value.off'),
  },
  {
    id: 'layout_use',
    label: ACMS.i18n('module_index.layout_use'),
    accessorKey: 'layout_use',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['layout_use']>) =>
      info.getValue() ? ACMS.i18n('module_index.layout_use_value.on') : ACMS.i18n('module_index.layout_use_value.off'),
  },
  {
    id: 'api_use',
    label: ACMS.i18n('module_index.api_use'),
    accessorKey: 'api_use',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<ModuleType, ModuleType['api_use']>) =>
      info.getValue() ? ACMS.i18n('module_index.api_use_value.on') : ACMS.i18n('module_index.api_use_value.off'),
  },
  {
    id: 'cache',
    label: ACMS.i18n('module_index.cache'),
    accessorKey: 'cache',
    enableSorting: true,
    type: 'number',
    getValue: (info: CellContext<ModuleType, ModuleType['cache']>) =>
      `${info.getValue()} ${ACMS.i18n('module_index.cache_suffix')}`,
  },
] as const satisfies Column<ModuleType>[];

/**
 * カラムの並び順
 */
export const MODULE_COLUMN_ORDER = [
  'id',
  'identifier',
  'status',
  'label',
  'name',
  'scope',
  'created_datetime',
  'updated_datetime',
  'custom_field',
  'layout_use',
  'api_use',
  'cache',
  'blog_id',
] as const satisfies ColumnOrder;
