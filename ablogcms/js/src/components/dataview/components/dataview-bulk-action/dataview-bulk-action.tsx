import { Table } from '@tanstack/react-table';
import type { BulkAction, DataviewComponents } from '../../types';
import HStack from '../../../stack/h-stack';

interface DataviewBulkActionProps<T> {
  data: T[];
  bulkActions?: BulkAction<T>[];
  table: Table<T>;
  className?: string;
  components: DataviewComponents<T>;
}

const DataviewBulkAction = <T,>({
  data,
  bulkActions = [],
  table,
  className = 'acms-admin-dataview-bulk-action',
  components,
}: DataviewBulkActionProps<T>) => {
  return (
    <div className={className}>
      <div>
        <components.BulkActions actions={bulkActions} data={data} table={table} />
      </div>
      <HStack>
        <span>{ACMS.i18n('dataview.actions.row_selection.info', { count: data.length })}</span>
        <button
          type="button"
          className="acms-admin-btn acms-admin-btn-text"
          onClick={() => {
            table.resetRowSelection();
          }}
        >
          {ACMS.i18n('dataview.actions.row_selection.clear')}
        </button>
      </HStack>
    </div>
  );
};

export default DataviewBulkAction;
