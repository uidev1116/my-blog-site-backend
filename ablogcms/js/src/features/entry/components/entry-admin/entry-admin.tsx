import { useCallback, useMemo, useState } from 'react';

import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { CellContext } from '@tanstack/react-table';
import useEntryAdminActions, { type UseEntryAdminActionsOptions } from '@features/entry/hooks/use-entry-admin-actions';
import useEntryAdminBulkActions, {
  type UseEntryAdminBulkActionsOptions,
} from '@features/entry/hooks/use-entry-admin-bulk-actions';
import useEntryAdminMenus, { type UseEntryAdminMenusOptions } from '@features/entry/hooks/use-entry-admin-menus';
import { nl2br } from '../../../../utils/react';
import DataView from '../../../../components/dataview/dataview';
import { components } from '../../../../components/dataview/components';
import {
  type DataviewComponents,
  type ColumnServiceInterface,
  type View,
  type ColumnConfig,
  type Column,
  type ColumnType,
  type GetValues,
  type CreateGetValuesOptions,
  CreateGetValues,
} from '../../../../components/dataview/types';
import type { EntriesResponse, EntryType } from '../../types';
import { ENTRY_COLUMNS } from '../../constants';
import EntryFilter from '../entry-filter';
import EntryCreateForm from '../entry-create-form';
import useEntryCustomColumnsSWR from '../../hooks/use-entry-custom-columns-swr';
import useEntryColumnConfigSWR from '../../hooks/use-entry-column-config-swr';
import { useAcmsContext } from '../../../../stores/acms';
import useEntriesSWR from '../../hooks/use-entries-swr';
import Alert from '../../../../components/alert/alert';
import { getOrderInfo, getSortFromContext } from '../../utils';
import { changeCategorySort, changeSort, changeUserSort } from '../../api';
import { notify } from '../../../../lib/notify';
import useUpdateEffect from '../../../../hooks/use-update-effect';

interface EntryAdminProps {
  /**
   * カスタムカラムの値を取得する関数
   */
  getValues?: (getValues: GetValues<EntryType>) => GetValues<EntryType>;
  /**
   * カスタムカラムの値を取得する関数のオプション
   */
  getValuesOptions?: CreateGetValuesOptions;
  /**
   * 各行のアクションをカスタマイズする関数
   */
  getActions?: UseEntryAdminActionsOptions['getActions'];
  /**
   * 一括操作のアクションをカスタマイズする関数
   */
  getBulkActions?: UseEntryAdminBulkActionsOptions['getBulkActions'];
  /**
   * メニューをカスタマイズする関数
   */
  getMenus?: UseEntryAdminMenusOptions['getMenus'];
  /**
   * 表示するカラムをカスタマイズする関数
   */
  getColumns?: (columns: Column<EntryType>[]) => Column<EntryType>[];
  /**
   * 行選択を有効にするかどうか
   */
  enableRowSelection?: React.ComponentProps<typeof DataView<EntryType>>['enableRowSelection'];
  /**
   * 行ドラッグを有効にするかどうか
   */
  enableRowDnD?: React.ComponentProps<typeof DataView<EntryType>>['enableRowDnD'];
  /**
   * 行IDを取得する関数
   */
  getRowId?: React.ComponentProps<typeof DataView<EntryType>>['getRowId'];
  /**
   * ページネーションを有効にするかどうか
   */
  enablePagination?: React.ComponentProps<typeof DataView<EntryType>>['enablePagination'];
  /**
   * カスタムコンポーネントをカスタマイズする関数
   */
  components?: Partial<DataviewComponents<EntryType>>;
}

const EmptyState: DataviewComponents<EntryType>['EmptyState'] = () => {
  const { context } = useAcmsContext();

  return (
    <components.EmptyState
      title={ACMS.i18n('entry_index.empty_state_title')}
      message={nl2br(ACMS.i18n('entry_index.empty_state_message'))}
    >
      <EntryCreateForm blogId={context.bid || parseInt(ACMS.Config.bid, 10)} categoryId={context.cid} />
    </components.EmptyState>
  );
};

function defaultGetRowId(row: EntryType) {
  return `${row.blog.id}:${row.id}`;
}

function createGetGetValue(options: Pick<EntryAdminProps, 'getValuesOptions' | 'getValues'>) {
  return function getGetValue(createGetValues: CreateGetValues<EntryType>) {
    return (info: CellContext<EntryType, unknown>): React.ReactNode => {
      let getValues = createGetValues(options.getValuesOptions);
      if (options.getValues) {
        getValues = typeof options.getValues === 'function' ? options.getValues(getValues) : options.getValues;
      }
      const getValue = getValues[info.column.columnDef.meta?.type as ColumnType];
      return typeof getValue === 'function' ? getValue(info) : (info.getValue() as React.ReactNode);
    };
  };
}

const EntryAdmin = (props: EntryAdminProps) => {
  const {
    getValues,
    getValuesOptions,
    getActions = (actions) => actions,
    getBulkActions = (bulkActions) => bulkActions,
    getMenus = (menus) => menus,
    getColumns = (columns) => columns,
    enableRowSelection,
    enableRowDnD,
    getRowId = defaultGetRowId,
    enablePagination = true,
    components = {},
  } = props;
  const { context } = useAcmsContext();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { data, isLoading: isEntriesLoading, invalidate, mutate } = useEntriesSWR(context, searchParams);
  const {
    customColumns,
    mutate: mutateCustomColumns,
    isLoading: isCustomColumnLoading,
    invalidate: invalidateCustomColumns,
  } = useEntryCustomColumnsSWR();
  const {
    config,
    mutate: mutateColumnConfig,
    isLoading: isColumnConfigLoading,
    invalidate: invalidateColumnConfig,
  } = useEntryColumnConfigSWR();

  const isLoading = useMemo(
    () => isCustomColumnLoading || isColumnConfigLoading || isEntriesLoading,
    [isColumnConfigLoading, isCustomColumnLoading, isEntriesLoading]
  );
  const columns = useMemo<Column<EntryType>[]>(() => {
    const columns = [...ENTRY_COLUMNS, ...(customColumns || [])];
    return getColumns(columns);
  }, [customColumns, getColumns]);
  const columnVisibility = useMemo<ColumnConfig['visibility']>(() => {
    const defaultCustomColumnVisibility = (customColumns || []).reduce((acc, column) => {
      // カスタムカラムのデフォルトの表示状態は非表示
      return { ...acc, [column.id]: false };
    }, {});
    return {
      ...defaultCustomColumnVisibility,
      ...config.visibility,
    };
  }, [customColumns, config.visibility]);

  const columnService: ColumnServiceInterface<EntryType> = useMemo(
    () => ({
      id: 'entry',
      columns,
      config: { visibility: columnVisibility, order: config.order },
      mutateCustomColumns,
      invalidateCustomColumns,
      mutateConfig: mutateColumnConfig,
      invalidateColumnConfig,
    }),
    [
      columns,
      columnVisibility,
      mutateCustomColumns,
      invalidateCustomColumns,
      config.order,
      mutateColumnConfig,
      invalidateColumnConfig,
    ]
  );

  const actions = useEntryAdminActions({
    getActions,
    context: { invalidate },
  });

  const bulkActions = useEntryAdminBulkActions({
    getBulkActions,
    context: { invalidate, bulkActions: data?.bulkActions || [], sort: data?.sort },
  });

  const menus = useEntryAdminMenus({
    getMenus,
    context: { context, searchParams, sort: data?.sort, columnService },
  });

  const view = useMemo<View>(() => {
    return {
      type: 'table',
      search: '',
      sort: getSortFromContext(context, searchParams),
      pageIndex: context.page || 0,
      pageSize: context.limit || parseInt(ACMS.Config.defaultLimit, 10),
      visibility: columnVisibility,
      order: ['sort', ...config.order],
    };
  }, [columnVisibility, searchParams, context, config.order]);

  const handleViewChange = (newView: View) => {
    const orderInfo = getOrderInfo(newView.sort, columns);
    if (orderInfo.sortFieldName) {
      searchParams.set('sortFieldName', orderInfo.sortFieldName);
    }
    navigate(
      ACMS.Library.acmsLink({
        ...context,
        page: newView.pageIndex,
        limit: newView.pageSize,
        order: orderInfo.order,
        searchParams,
      })
    );
  };

  const handleSubmit = useCallback(
    (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const form = event.currentTarget;
      const formData = new FormData(form, (event.nativeEvent as SubmitEvent).submitter);
      const intent = formData.get('intent') as 'search' | 'clear';

      if (intent === 'clear') {
        form.reset();
        navigate(ACMS.Library.acmsLink({ bid: ACMS.Config.bid, admin: ACMS.Config.admin || 'entry_index' }));
        return;
      }

      const { context, searchParams } = ACMS.Library.createAcmsContextFromFormData(formData);
      navigate(ACMS.Library.acmsLink({ ...context, searchParams }));
    },
    [navigate]
  );

  const handleDragEnd = useCallback(
    async (entries: EntryType[]) => {
      if (data?.sort.type === undefined) {
        return;
      }
      // eslint-disable-next-line no-console
      const formData = new FormData();
      const sortNumbers = entries
        .map((entry) => entry.sort)
        .sort((a, b) => (view.sort?.direction === 'asc' ? a - b : b - a));

      entries.forEach((entry, index) => {
        formData.append('checks[]', getRowId(entry));
        formData.append(`sort-${entry.id}`, sortNumbers[index].toString());
      });

      async function sortFn(formData: FormData, data: EntriesResponse): Promise<EntriesResponse> {
        await {
          entry: changeSort,
          user: changeUserSort,
          category: changeCategorySort,
        }[data.sort.type](formData);
        return { ...data, entries };
      }
      try {
        await mutate(sortFn(formData, data), {
          optimisticData: { ...data, entries },
          populateCache: true,
        });
        notify.info(ACMS.i18n('entry_index.bulk_action.feedback.order.success'));
      } catch (error) {
        notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.order.error'));
        console.error(error); // eslint-disable-line no-console
      }
    },
    [view.sort?.direction, data, mutate, getRowId]
  );

  const [isAlertVisible, setIsAlertVisible] = useState(true);

  // data.sort の変更を監視して isAlertVisible をリセット
  useUpdateEffect(() => {
    if (data?.sort.enabled && data.sort.type) {
      setIsAlertVisible(true);
    }
    // location.key を監視しているのは、同一のurlに遷移したときに isAlertVisible をリセットするため
  }, [data?.sort.enabled, data?.sort.type, location.key]);
  return (
    <div>
      <EntryFilter
        columns={columns}
        ignoredFilters={data?.ignoredFilters}
        sortType={data?.sort.enabled ? data.sort.type : undefined}
        onSubmit={handleSubmit}
      />
      {data?.sort.enabled && data.sort.type && isAlertVisible && (
        <Alert type="info" onClose={() => setIsAlertVisible(false)}>
          {ACMS.i18n(`entry_index.sort_mode.alert.${data.sort.type}`, {
            name: data.sort.context?.name,
          })}
        </Alert>
      )}
      <DataView<EntryType>
        view={view}
        onViewChange={handleViewChange}
        data={data?.entries || []}
        columns={columns}
        actions={actions}
        bulkActions={bulkActions}
        menus={menus}
        enableRowSelection={typeof enableRowSelection === 'boolean' ? enableRowSelection : bulkActions.length > 0}
        enableRowDnD={typeof enableRowDnD === 'boolean' ? enableRowDnD : data?.sort.enabled}
        getRowId={getRowId}
        getGetValue={createGetGetValue({ getValuesOptions, getValues })}
        onDragEnd={handleDragEnd}
        enablePagination={enablePagination}
        paginationInfo={{
          totalItems: data?.totalItems || 0,
          pageSizes: ACMS.Config.limitOptions.map((limit) => parseInt(limit, 10)),
        }}
        components={{
          EmptyState,
          ...components,
        }}
        isLoading={isLoading}
        aria-label={ACMS.i18n('entry_index.dataview.label')}
      />
    </div>
  );
};

export default EntryAdmin;
