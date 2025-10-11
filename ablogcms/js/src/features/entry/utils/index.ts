import { Column, SortDirection, type View } from '../../../components/dataview/types';
import { AcmsContext } from '../../../lib/acmsPath/types';
import { ENTRY_ORDER_COLUMNS } from '../constants';
import { EntryType } from '../types';

interface OrderInfo {
  order: AcmsContext['order'];
  sortFieldName?: string;
}

export function getOrderInfo(sort: View['sort'], columns: Column<EntryType>[]): OrderInfo {
  if (sort === undefined) {
    return { order: undefined };
  }

  if (ENTRY_ORDER_COLUMNS.some((column) => column === sort?.id)) {
    return {
      order: `${sort.id}-${sort.direction}`,
    };
  }
  const column = columns.find((column) => column.id === sort?.id);
  if (column) {
    return {
      order: `${column.type === 'number' ? 'intfield' : 'field'}-${sort.direction}`,
      sortFieldName: sort.id,
    };
  }

  return { order: `id-${sort.direction}` };
}

export function getSortFromContext(context: AcmsContext, searchParams: URLSearchParams): NonNullable<View['sort']> {
  if (context.order === undefined) {
    return { id: 'datetime', direction: 'desc' as SortDirection };
  }

  const [id, direction] = context.order.split('-');
  if (!['field', 'intfield'].includes(id)) {
    return { id, direction: direction as SortDirection };
  }
  const sortFieldName = searchParams.get('sortFieldName');
  if (sortFieldName) {
    return { id: sortFieldName, direction: direction as SortDirection };
  }

  if (context.field?.getFields() && context.field.getFields().length > 0) {
    const field = context.field.getFields()[0];
    return { id: field.key, direction: direction as SortDirection };
  }
  return { id: 'entry_id', direction: direction as SortDirection };
}

export function createEntryEditLink(entry: EntryType, defaultAdmin: string) {
  const { entryEditPageType } = ACMS.Config;
  if (entryEditPageType === 'admin' || entry.approval !== undefined) {
    return ACMS.Library.acmsLink({
      bid: entry.blog.id,
      cid: entry.category?.id,
      eid: entry.id,
      admin: 'entry_editor',
    });
  }
  if (entryEditPageType === 'front') {
    return ACMS.Library.acmsLink({
      bid: entry.blog.id,
      cid: entry.category?.id,
      eid: entry.id,
      admin: 'entry-edit',
    });
  }
  return ACMS.Library.acmsLink({
    bid: entry.blog.id,
    cid: entry.category?.id,
    eid: entry.id,
    admin: defaultAdmin,
  });
}
