import { Table } from '@tanstack/react-table';
import { useCallback } from 'react';
import VisuallyHidden from '@components/visually-hidden';
import HStack from '../../../stack/h-stack';
import { Pagination, PaginationRoot, PaginationSummary } from '../../../pagination/pagination';
import { PaginationInfo } from '../../types';

interface DataviewPaginationProps<T> {
  table: Table<T>;
  className?: string;
  paginationInfo: PaginationInfo;
  enablePagination?: boolean;
}

const DataviewPagination = <T,>({
  table,
  className = 'acms-admin-dataview-pagination',
  paginationInfo,
  enablePagination = false,
}: DataviewPaginationProps<T>) => {
  const handlePageChange = useCallback(
    (page: number) => {
      table.setPageIndex(page - 1); // tanstack-table は 0-indexed なので -1 する
    },
    [table]
  );

  const handleLimitChange = useCallback(
    (event: React.ChangeEvent<HTMLSelectElement>) => {
      table.setPageSize(parseInt(event.target.value, 10));
    },
    [table]
  );
  return (
    <div className={className}>
      {paginationInfo.pageSizes.length > 0 && (
        <div className="acms-admin-form">
          <VisuallyHidden asChild>
            <label htmlFor="filter-limit">{ACMS.i18n('dataview.actions.limit_label')}</label>
          </VisuallyHidden>
          <select
            defaultValue={table.getState().pagination.pageSize}
            form="entry-filter-form"
            name="limit"
            id="filter-limit"
            onChange={handleLimitChange}
            key={table.getState().pagination.pageSize}
          >
            {paginationInfo.pageSizes.map((pageSize) => (
              <option key={pageSize} value={pageSize}>
                {ACMS.i18n('dataview.actions.limit', { limit: pageSize })}
              </option>
            ))}
          </select>
        </div>
      )}
      {enablePagination && (
        <PaginationRoot
          page={table.getState().pagination.pageIndex + 1}
          pageSize={table.getState().pagination.pageSize}
          total={Math.ceil(paginationInfo.totalItems / table.getState().pagination.pageSize)}
          totalItems={paginationInfo.totalItems}
          withNumbers={false}
          onChange={handlePageChange}
        >
          <HStack>
            <div>
              <PaginationSummary />
            </div>
            <div>
              <Pagination aria-label={ACMS.i18n('dataview.pagination.top.label')} />
            </div>
          </HStack>
        </PaginationRoot>
      )}
    </div>
  );
};

export default DataviewPagination;
