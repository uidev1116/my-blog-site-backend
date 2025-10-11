import type { ColumnType } from '../types';

export const CUSTOM_COLUMN_TYPE_OPTIONS: { label: string; value: ColumnType }[] = [
  { value: 'text', label: ACMS.i18n('dataview.custom_column.type.text') },
  { value: 'textarea', label: ACMS.i18n('dataview.custom_column.type.textarea') },
  { value: 'number', label: ACMS.i18n('dataview.custom_column.type.number') },
  { value: 'select', label: ACMS.i18n('dataview.custom_column.type.select') },
  { value: 'checkbox', label: ACMS.i18n('dataview.custom_column.type.checkbox') },
  { value: 'radio', label: ACMS.i18n('dataview.custom_column.type.radio') },
  { value: 'datetime', label: ACMS.i18n('dataview.custom_column.type.datetime') },
  { value: 'image', label: ACMS.i18n('dataview.custom_column.type.image') },
  { value: 'file', label: ACMS.i18n('dataview.custom_column.type.file') },
  { value: 'media', label: ACMS.i18n('dataview.custom_column.type.media') },
  { value: 'rich-editor', label: ACMS.i18n('dataview.custom_column.type.rich-editor') },
  { value: 'block-editor', label: ACMS.i18n('dataview.custom_column.type.block-editor') },
  { value: 'group', label: ACMS.i18n('dataview.custom_column.type.group') },
] as const;

export const FilterableColumnTypes = [
  'text',
  'number',
  'datetime',
  'select',
  'checkbox',
  'radio',
] as const satisfies ColumnType[];
