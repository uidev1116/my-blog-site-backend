import { type CellContext } from '@tanstack/react-table';
import dayjs from 'dayjs';
import { Link } from 'react-router';
import VisuallyHidden from '@components/visually-hidden';
import {
  type Column,
  type OptionData,
  type ColumnVisibility,
  type ColumnOrder,
} from '../../../components/dataview/types';
import { type EntryType } from '../types';
import VStack from '../../../components/stack/v-stack';
import ConditionalWrap from '../../../components/conditional-wrap/conditional-wrap';
import { truncateUrl } from '../../../utils/string';
import { Tooltip } from '../../../components/tooltip';

export const ENTRY_STATUSES = [
  { value: 'open', label: ACMS.i18n('entry.status.open') },
  { value: 'draft', label: ACMS.i18n('entry.status.draft') },
  { value: 'close', label: ACMS.i18n('entry.status.close') },
  { value: 'trash', label: ACMS.i18n('entry.status.trash') },
] as const;

export const ENTRY_STATEUS_VARIANTS = {
  open: 'info',
  draft: 'warning',
  close: 'danger',
  trash: 'danger',
} as const;

export const ENTRY_SESSIONS = [
  { value: 'public', label: ACMS.i18n('entry.session.public') },
  { value: 'expiration', label: ACMS.i18n('entry.session.expiration') },
  { value: 'future', label: ACMS.i18n('entry.session.future') },
] as const;

export const ENTRY_APPROVAL = [
  { value: 'pre_approval', label: ACMS.i18n('entry.approval.pre_approval') },
  { value: 'none', label: ACMS.i18n('entry.approval.none') },
] as const;

export const ENTRY_APPROVAL_VARIANTS = {
  pre_approval: 'warning',
  none: 'info',
} as const;

export const ENTRY_FORM_STATUSES = [
  { value: 'open', label: ACMS.i18n('entry.form_status.open') },
  { value: 'close', label: ACMS.i18n('entry.form_status.close') },
] as const;

export const ENTRY_FORM_STATEUS_VARIANTS = {
  open: 'info',
  close: 'danger',
} as const;

export const ENTRY_COLUMNS = [
  {
    id: 'sort',
    label: ACMS.i18n('entry_index.sort'),
    accessorKey: 'sort',
    enableSorting: true,
    enableHiding: false,
    type: 'number',
    getValue: (info: CellContext<EntryType, EntryType['sort']>) => (
      <span className="acms-admin-form">
        <label>
          <VisuallyHidden>{ACMS.i18n('entry_index.sort')}</VisuallyHidden>
          <input
            type="text"
            name={`sort-${info.row.original.id}`}
            defaultValue={info.getValue()}
            size={4}
            form="bulk-action-form"
            key={info.getValue()}
          />
        </label>
      </span>
    ),
  },
  {
    id: 'id',
    label: ACMS.i18n('entry_index.id'),
    accessorKey: 'id',
    enableSorting: true,
    type: 'number',
    getValue: (info: CellContext<EntryType, EntryType['id']>) => (
      <span className="acms-admin-label">{info.getValue()}</span>
    ),
  },
  {
    id: 'status',
    label: ACMS.i18n('entry_index.status'),
    accessorKey: 'status',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['status']>) => {
      const status = ENTRY_STATUSES.find((status) => status.value === info.getValue());
      if (status === undefined) {
        return <span className="acms-admin-label">{info.getValue()}</span>;
      }
      return (
        <span className={`acms-admin-label acms-admin-label-${ENTRY_STATEUS_VARIANTS[status.value]}`}>
          {status.label}
        </span>
      );
    },
  },
  {
    id: 'approval',
    label: ACMS.i18n('entry_index.approval'),
    accessorKey: 'approval',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['approval']>) => {
      const status = ENTRY_APPROVAL.find((status) => status.value === info.getValue());
      if (status === undefined) {
        return <span className="acms-admin-label">{info.getValue()}</span>;
      }
      return (
        <span className={`acms-admin-label acms-admin-label-${ENTRY_APPROVAL_VARIANTS[status.value]}`}>
          {status.label}
        </span>
      );
    },
  },
  {
    id: 'form_status',
    label: ACMS.i18n('entry_index.form_status'),
    accessorKey: 'form_status',
    enableSorting: false,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['form_status']>) => {
      const status = ENTRY_FORM_STATUSES.find((status) => status.value === info.getValue());
      if (status === undefined) {
        return <span className="acms-admin-label">{info.getValue()}</span>;
      }
      return (
        <span className={`acms-admin-label acms-admin-label-${ENTRY_FORM_STATEUS_VARIANTS[status.value]}`}>
          {status.label}
        </span>
      );
    },
  },
  {
    id: 'title',
    label: ACMS.i18n('entry_index.title'),
    accessorKey: 'title',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['title']>) => (
      <a href={info.row.original.url}>{info.getValue()}</a>
    ),
  },
  {
    id: 'code',
    label: ACMS.i18n('entry_index.code'),
    accessorKey: 'code',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['code']>) => {
      return info.getValue();
    },
  },
  {
    id: 'link',
    label: ACMS.i18n('entry_index.link'),
    accessorKey: 'link',
    enableSorting: true,
    type: 'text',
    getValue: (info: CellContext<EntryType, EntryType['link']>) => {
      return (
        <>
          <a
            href={info.getValue()}
            target="_blank"
            rel="noreferrer"
            data-tooltip-id={`entry-link-${info.row.original.id}`}
            data-tooltip-content={info.getValue()}
          >
            {truncateUrl(info.getValue(), ACMS.Config.entryAdminConfig.linkMaxLength)}
          </a>
          <Tooltip id={`entry-link-${info.row.original.id}`} />
        </>
      );
    },
  },
  {
    id: 'datetime',
    label: ACMS.i18n('entry_index.datetime'),
    accessorKey: 'datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<EntryType, EntryType['datetime']>) =>
      dayjs(info.getValue()).format('YYYY/MM/DD HH:mm'),
  },
  {
    id: 'start_datetime',
    label: ACMS.i18n('entry_index.start_datetime'),
    accessorKey: 'start_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<EntryType, EntryType['start_datetime']>) =>
      dayjs(info.getValue()).format('YYYY/MM/DD HH:mm'),
  },
  {
    id: 'end_datetime',
    label: ACMS.i18n('entry_index.end_datetime'),
    accessorKey: 'end_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<EntryType, EntryType['end_datetime']>) =>
      dayjs(info.getValue()).format('YYYY/MM/DD HH:mm'),
  },
  {
    id: 'updated_datetime',
    label: ACMS.i18n('entry_index.updated_datetime'),
    accessorKey: 'updated_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<EntryType, EntryType['updated_datetime']>) =>
      dayjs(info.getValue()).format('YYYY/MM/DD HH:mm'),
  },
  {
    id: 'posted_datetime',
    label: ACMS.i18n('entry_index.posted_datetime'),
    accessorKey: 'posted_datetime',
    enableSorting: true,
    type: 'datetime',
    getValue: (info: CellContext<EntryType, EntryType['posted_datetime']>) =>
      dayjs(info.getValue()).format('YYYY/MM/DD HH:mm'),
  },
  {
    id: 'primary_image',
    label: ACMS.i18n('entry_index.primary_image'),
    accessorKey: 'primary_image',
    enableSorting: false,
    type: 'object',
    getValue: (info: CellContext<EntryType, EntryType['primary_image']>) => {
      const primaryImage = info.getValue();
      if (primaryImage == null) {
        return <>-</>;
      }
      if (primaryImage.thumbnail === '') {
        return <>-</>;
      }

      return (
        <div className="acms-admin-cell-image">
          <img src={`${primaryImage.thumbnail}`} alt={primaryImage.alt || info.row.original.title} />
        </div>
      );
    },
  },
  {
    id: 'members_only',
    label: ACMS.i18n('entry_index.members_only'),
    accessorKey: 'members_only',
    enableSorting: false,
    type: 'select',
    getValue: (info: CellContext<EntryType, EntryType['members_only']>) =>
      info.getValue()
        ? ACMS.i18n('entry_index.members_only_value.on')
        : ACMS.i18n('entry_index.members_only_value.off'),
  },
  {
    id: 'indexing',
    label: ACMS.i18n('entry_index.indexing'),
    accessorKey: 'indexing',
    enableSorting: false,
    type: 'select',
    getValue: (info: CellContext<EntryType, EntryType['indexing']>) =>
      info.getValue() ? ACMS.i18n('entry_index.indexing_value.on') : ACMS.i18n('entry_index.indexing_value.off'),
  },
  {
    id: 'tags',
    label: ACMS.i18n('entry_index.tag'),
    accessorKey: 'tags',
    enableSorting: false,
    type: 'array',
    getValue: (info: CellContext<EntryType, EntryType['tags']>) => {
      if (info.getValue().length === 0) {
        return <>-</>;
      }
      return (
        <VStack asChild align="start" spacing="0.25rem">
          <ul className="acms-admin-list-nostyle">
            {info.getValue().map((tag) => (
              <li key={tag}>
                <span className="acms-admin-label acms-admin-label-info">{tag}</span>
              </li>
            ))}
          </ul>
        </VStack>
      );
    },
  },
  {
    id: 'category',
    label: ACMS.i18n('entry_index.category'),
    accessorKey: 'category',
    enableSorting: false,
    type: 'object',
    getValue: (info: CellContext<EntryType, EntryType['category']>) => {
      const category = info.getValue();
      if (category === undefined) {
        return <>-</>;
      }

      return (
        <Link to={new URL(category.url).pathname}>
          <i className="acms-admin-icon acms-admin-icon-category" aria-hidden />
          {category.name}
        </Link>
      );
    },
  },
  {
    id: 'blog',
    label: ACMS.i18n('entry_index.blog'),
    accessorKey: 'blog',
    enableSorting: false,
    type: 'object',
    getValue: (info: CellContext<EntryType, EntryType['blog']>) => {
      const url = new URL(info.getValue().url);
      return (
        <Link to={`${url.pathname}${url.search}`}>
          <i className="acms-admin-icon acms-admin-icon-blog" aria-hidden />
          {info.getValue().name}
        </Link>
      );
    },
  },
  {
    id: 'user',
    label: ACMS.i18n('entry_index.user'),
    accessorKey: 'user',
    enableSorting: false,
    type: 'object',
    getValue: (info: CellContext<EntryType, EntryType['user']>) => {
      return (
        <ConditionalWrap
          condition={info.getValue().url != null}
          wrap={(children) => <Link to={new URL(info.getValue().url).pathname}>{children}</Link>}
        >
          <img
            src={`${ACMS.Config.ARCHIVES_DIR}${info.getValue().icon}`}
            width="28"
            height="28"
            alt=""
            className="acms-admin-user acms-admin-margin-right-small acms-admin-cell-text-middle"
          />
          {info.getValue().name}
        </ConditionalWrap>
      );
    },
  },
  {
    id: 'form',
    label: ACMS.i18n('entry_index.form'),
    accessorKey: 'form',
    enableSorting: false,
    type: 'object',
    getValue: (info: CellContext<EntryType, EntryType['form']>) => {
      const form = info.getValue();
      if (form === undefined) {
        return <>-</>;
      }
      return (
        <>
          {form.name} ({form.code})
        </>
      );
    },
  },
] as const satisfies Column<EntryType>[];

export const ENTRY_COLUMN_VISIBILITY = {
  id: true,
  sort: true,
  status: true,
  approval: false,
  form_status: false,
  title: true,
  code: true,
  link: false,
  datetime: true,
  updated_datetime: true,
  posted_datetime: true,
  start_datetime: true,
  end_datetime: true,
  primary_image: true,
  members_only: false,
  indexing: true,
  tags: true,
  category: true,
  blog: true,
  user: true,
  form: false,
} as const satisfies ColumnVisibility;

export const ENTRY_COLUMN_ORDER = [
  'id',
  'status',
  'approval',
  'form_status',
  'title',
  'code',
  'link',
  'datetime',
  'updated_datetime',
  'posted_datetime',
  'start_datetime',
  'end_datetime',
  'primary_image',
  'members_only',
  'indexing',
  'tags',
  'category',
  'blog',
  'user',
  'form',
] as const satisfies ColumnOrder;

export const FILTERABLE_ENTRY_COLUMN_IDS = [
  'datetime',
  'start_datetime',
  'end_datetime',
  'posted_datetime',
  'updated_datetime',
  'members_only',
  'indexing',
] as const satisfies (typeof ENTRY_COLUMNS)[number]['id'][];

export const ENTRY_OPTION_DATA = {
  members_only: [
    { value: 'on', label: ACMS.i18n('entry_index.members_only_value.on') },
    { value: 'off', label: ACMS.i18n('entry_index.members_only_value.off') },
  ],
  indexing: [
    { value: 'on', label: ACMS.i18n('entry_index.indexing_value.on') },
    { value: 'off', label: ACMS.i18n('entry_index.indexing_value.off') },
  ],
} as const satisfies OptionData<EntryType>;

// php/ACMS/Filter.php の entryOrderメソッドと一致させる
export const ENTRY_ORDER_COLUMNS = [
  'sort',
  'id',
  'code',
  'status',
  'title',
  'link',
  'datetime',
  'start_datetime',
  'end_datetime',
  'updated_datetime',
  'posted_datetime',
  'summary_range',
  'indexing',
  'primary_image',
  'category_id',
  'blog_id',
  'user_id',
] as const;
