import { DataviewComponents } from './types';
import DataviewActions from './components/dataview-actions/dataview-actions';
import EmptyState from './components/empty-state/empty-state';
import TableLoader from './components/loaders/table-loader';
import Spinner from './components/ui/spinner';
import DataviewBulkAction from './components/dataview-bulk-action/dataview-bulk-action';
import DataviewPagination from './components/dataview-pagination/dataview-pagination';
import BulkActions from './components/bulk-actions/bulk-actions';
import DataviewMenu from './components/dataview-menu/dataview-menu';
import MenuList from './components/menu-list/menu-list';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const components: DataviewComponents<any> = {
  DataviewActions,
  HeaderContents: () => null,
  EmptyState,
  TableLoader,
  Spinner,
  DataviewBulkAction,
  DataviewPagination,
  BulkActions,
  DataviewMenu,
  MenuList,
};
