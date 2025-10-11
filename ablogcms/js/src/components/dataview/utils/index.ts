import { type Column as TanstackTableColumn } from '@tanstack/react-table';
import {
  isCustomAccessorColumn,
  type AccessorColumn,
  type Column,
  type ColumnVisibility,
  type RowData,
  ColumnOrder,
} from '../types';

/**
 * カラムのアクセサキー（データのキーとなる文字列）を取得する
 * @example
 * const column = {
 *   id: 'id',
 *   accessorKey: 'accessorKey',
 *   type: 'text',
 * };
 * const accessorKey = getColumnAccessorKey(column);
 * console.log(accessorKey); // 'accessorKey'
 */
export function getColumnAccessorKey<T>(column: AccessorColumn<T>): string {
  return column.accessorKey || column.id;
}

/**
 * カラムの順番を表示順に並び替える
 */
export function sortColumnsByColumnOrder<T extends RowData>(columns: Column<T>[], columnOrder: ColumnOrder) {
  return [...columns].sort((a, b) => {
    const aIndex = columnOrder.indexOf(a.id);
    const bIndex = columnOrder.indexOf(b.id);
    if (aIndex === -1 && bIndex === -1) {
      return 0;
    }
    if (aIndex === -1) {
      return 1;
    }
    if (bIndex === -1) {
      return -1;
    }
    return aIndex - bIndex;
  });
}

/**
 * カラムの順番を表示状態に従って並び替える
 * 表示されているカラムは先頭に、非表示のカラムは後ろに並べる
 */
export function sortColumnsByColumnVisibility<T extends RowData>(
  columns: Column<T>[],
  columnVisibility: ColumnVisibility
) {
  return [...columns].sort((a, b) => {
    const aVisible = columnVisibility[a.id] ?? false;
    const bVisible = columnVisibility[b.id] ?? false;
    if (aVisible && bVisible) {
      // どちらも表示されている場合は、そのまま
      return 0;
    }

    return aVisible ? -1 : 1; // 表示されているカラムを先頭に
  });
}

/**
 * フィルターの選択肢を並び替える
 */
export function sortColumnsForFilterSelect<T extends RowData>(columns: AccessorColumn<T>[]) {
  // カスタムカラムの選択肢を先に並べ替え
  return [...columns].sort((a, b) => {
    if (isCustomAccessorColumn(a) && isCustomAccessorColumn(b)) {
      return 0;
    }
    return isCustomAccessorColumn(a) ? -1 : 1;
  });
}

// These are the important styles to make sticky column pinning work!
// Apply styles like this using your CSS strategy of choice with this kind of logic to head cells, data cells, footer cells, etc.
export function getCommonPinningStyles<T extends RowData>(column: TanstackTableColumn<T>): React.CSSProperties {
  const isPinned = column.getIsPinned();
  const isLastLeftPinnedColumn = isPinned === 'left' && column.getIsLastColumn('left');
  const isFirstRightPinnedColumn = isPinned === 'right' && column.getIsFirstColumn('right');

  return {
    borderRight: isLastLeftPinnedColumn ? 'var(--acms-admin-table-admin-border)' : undefined,
    borderLeft: isFirstRightPinnedColumn ? 'var(--acms-admin-table-admin-border)' : undefined,
    left: isPinned === 'left' ? `${column.getStart('left')}px` : undefined,
    right: isPinned === 'right' ? `${column.getAfter('right')}px` : undefined,
    position: isPinned ? 'sticky' : 'relative',
    zIndex: isPinned ? 1 : 0,
  };
}
