import { useCallback, useMemo } from 'react';
import classnames from 'classnames';
import { Icon } from '@components/icon';
import { range } from '../../utils';
import { PaginationContext, usePaginationContext } from './stores';
import { formatNumber } from '../../utils/number';

interface PaginationRootProps {
  /**
   * Current page number
   */
  page: number;

  /**
   * Total number of items
   */
  totalItems: number;

  /**
   * Number of items per page
   */
  pageSize: number;

  /**
   *  Total number of pages
   */
  total: number;

  /**
   * 前後に表示するページ数
   */
  deltaSize?: number;

  /**
   * ページ変更時のコールバック
   */
  onChange: (page: number, event: React.MouseEvent) => void;

  /**
   * ページ番号のボタンを表示するかどうか
   */
  withNumbers?: boolean;

  /**
   * ReactNode
   */
  children?: React.ReactNode;
}

const PaginationRoot = ({
  page,
  total,
  totalItems,
  deltaSize = 3,
  pageSize = 20,
  onChange,
  withNumbers = true,
  children,
}: PaginationRootProps) => {
  const handleChange = useCallback(
    (page: number, event: React.MouseEvent) => {
      onChange(page, event);
    },
    [onChange]
  );

  const pages = useMemo(
    () => [
      ...range(page - deltaSize, page).filter((page) => page >= 1),
      ...range(page, page + deltaSize + 1).filter((page) => page <= total),
    ],
    [page, total, deltaSize]
  );

  const value = useMemo(
    () => ({ page, total, onChange: handleChange, pages, pageSize, totalItems, withNumbers }),
    [page, total, handleChange, pages, pageSize, totalItems, withNumbers]
  );

  if (totalItems <= 0) {
    return null;
  }

  return <PaginationContext.Provider value={value}>{children}</PaginationContext.Provider>;
};

const Pagination = ({ className, ...props }: React.HTMLAttributes<HTMLElement>) => {
  const { page, pages, total, withNumbers, onChange } = usePaginationContext();
  if (total <= 1) {
    return null;
  }

  return (
    <nav
      className={classnames('acms-admin-pager-container', className)}
      aria-label={ACMS.i18n('pagination.label')}
      {...props}
    >
      <ul className="acms-admin-pager">
        {(page > 1 || !withNumbers) && (
          <li>
            <button
              type="button"
              onClick={(event) => onChange(page - 1, event)}
              disabled={page === 1} // 1ページ目の場合は無効
              aria-label={ACMS.i18n('pagination.prev')}
            >
              <Icon name="arrow_back_ios_new" />
            </button>
          </li>
        )}
        {withNumbers && pages[0] > 1 && (
          <>
            <li>
              <button type="button" onClick={(event) => onChange(1, event)}>
                1
              </button>
            </li>
            <li>...</li>
          </>
        )}
        {withNumbers &&
          pages.map((num) => (
            <li key={`pagination-${num}`} className={num === page ? 'cur' : undefined}>
              <button type="button" onClick={(event) => onChange(num, event)}>
                {num}
              </button>
            </li>
          ))}
        {withNumbers && pages[pages.length - 1] < total && (
          <>
            <li>...</li>
            <li>
              <button type="button" onClick={(event) => onChange(total, event)}>
                {total}
              </button>
            </li>
          </>
        )}
        {(page < total || !withNumbers) && (
          <li>
            <button
              type="button"
              onClick={(event) => onChange(page + 1, event)}
              disabled={page === total} // 最終ページの場合は無効
              aria-label={ACMS.i18n('pagination.next')}
            >
              <Icon name="arrow_forward_ios" />
            </button>
          </li>
        )}
      </ul>
    </nav>
  );
};

const PaginationSummary = () => {
  const { page, pageSize, totalItems } = usePaginationContext();
  return (
    <div className="acms-admin-itemsAmount-container">
      <p>
        {formatNumber((page - 1) * pageSize + 1)}
        {ACMS.i18n('pagination.items')} - {formatNumber(page * pageSize > totalItems ? totalItems : page * pageSize)}
        {ACMS.i18n('pagination.items')} / {ACMS.i18n('pagination.all')}
        {formatNumber(totalItems)}
        {ACMS.i18n('pagination.items')}
      </p>
    </div>
  );
};

export { PaginationRoot, Pagination, PaginationSummary };
