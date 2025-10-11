import { Table } from '@tanstack/react-table';
import type { BulkAction, DataviewComponents, Menu, PaginationInfo } from '../../types';

interface DataviewActionsProps<T> {
  data: T[];
  bulkActions?: BulkAction<T>[];
  menus?: Menu<T>[];
  table: Table<T>;
  className?: string;
  paginationInfo: PaginationInfo;
  enablePagination?: boolean;
  components: DataviewComponents<T>;
}

const DataviewActions = <T,>({
  data,
  bulkActions = [],
  menus = [],
  table,
  className = 'acms-admin-dataview-actions',
  paginationInfo,
  enablePagination = false,
  components,
}: DataviewActionsProps<T>) => {
  const selectedData = table.getSelectedRowModel().rows.map((row) => row.original);

  return (
    <div className={className}>
      {bulkActions.length > 0 && selectedData.length > 0 ? (
        <components.DataviewBulkAction
          bulkActions={bulkActions}
          data={selectedData}
          table={table}
          className="acms-admin-dataview-bulk-action"
          components={components}
        />
      ) : (
        <components.DataviewPagination
          className="acms-admin-dataview-pagination"
          paginationInfo={paginationInfo}
          enablePagination={enablePagination}
          table={table}
        />
      )}
      <components.DataviewMenu
        className="acms-admin-dataview-menu"
        menus={menus}
        data={data}
        table={table}
        components={components}
      />
    </div>
  );
};

export default DataviewActions;
