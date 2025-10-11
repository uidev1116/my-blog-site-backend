import { useCallback, useMemo } from 'react';

import { useNavigate, useSearchParams } from 'react-router';
import useModuleAdminActions from '@features/module/hooks/use-module-admin-actions';
import useModuleAdminBulkActions from '@features/module/hooks/use-module-admin-bulk-actions';
import useModuleAdminMenus from '@features/module/hooks/use-module-admin-menus';
import { nl2br } from '../../../../utils/react';
import DataView from '../../../../components/dataview/dataview';
import { components } from '../../../../components/dataview/components';
import {
  type DataviewComponents,
  type View,
  type Column,
  type SortDirection,
} from '../../../../components/dataview/types';
import type { ModuleType } from '../../types';
import { MODULE_COLUMNS, MODULE_COLUMN_ORDER } from '../../constants';
import ModuleFilter from '../module-filter';
import ModuleCreateForm from '../module-create-form';
import { useAcmsContext } from '../../../../stores/acms';
import useModulesSWR from '../../hooks/use-modules-swr';

const EmptyState: DataviewComponents<ModuleType>['EmptyState'] = () => {
  const { context } = useAcmsContext();

  return (
    <components.EmptyState
      title={ACMS.i18n('module_index.empty_state_title')}
      message={nl2br(ACMS.i18n('module_index.empty_state_message'))}
    >
      <ModuleCreateForm blogId={context.bid || parseInt(ACMS.Config.bid, 10)} />
    </components.EmptyState>
  );
};

function getRowId(row: ModuleType) {
  return `${row.blog_id}:${row.id}`;
}

const ModuleAdmin = () => {
  const { context } = useAcmsContext();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { data, isLoading: isModulesLoading, invalidate } = useModulesSWR(context, searchParams);

  const isLoading = useMemo(() => isModulesLoading, [isModulesLoading]);

  const columns = useMemo<Column<ModuleType>[]>(() => {
    const columns = [...MODULE_COLUMNS];
    return ACMS.Config.moduleAdminConfig.getColumns(columns);
  }, []);

  const actions = useModuleAdminActions({
    getActions: ACMS.Config.moduleAdminConfig.getActions,
    context: { invalidate, searchParams },
  });

  const bulkActions = useModuleAdminBulkActions({
    getBulkActions: ACMS.Config.moduleAdminConfig.getBulkActions,
    context: { invalidate, searchParams, bulkActions: data?.bulkActions || [] },
  });

  const menus = useModuleAdminMenus({
    getMenus: ACMS.Config.moduleAdminConfig.getMenus,
    context: { context, searchParams },
  });

  const view = useMemo<View>(() => {
    let sort: NonNullable<View['sort']> = { id: 'updated_datetime', direction: 'desc' as SortDirection };
    if (context.order !== undefined) {
      const [id, direction] = context.order.split('-');
      sort = { id, direction: direction as SortDirection };
    }
    return {
      type: 'table',
      search: '',
      sort,
      pageIndex: context.page || 0,
      pageSize: context.limit || parseInt(ACMS.Config.defaultLimit, 10),
      order: MODULE_COLUMN_ORDER,
    };
  }, [context]);

  const handleViewChange = (newView: View) => {
    navigate(
      ACMS.Library.acmsLink({
        ...context,
        page: newView.pageIndex,
        limit: newView.pageSize,
        order: newView.sort ? `${newView.sort.id}-${newView.sort.direction}` : undefined,
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
        navigate(ACMS.Library.acmsLink({ bid: ACMS.Config.bid, admin: 'module_index' }));
        return;
      }

      const { context, searchParams } = ACMS.Library.createAcmsContextFromFormData(formData);
      navigate(ACMS.Library.acmsLink({ ...context, searchParams }));
    },
    [navigate]
  );

  return (
    <div>
      <ModuleFilter onSubmit={handleSubmit} />
      <DataView<ModuleType>
        view={view}
        onViewChange={handleViewChange}
        data={data?.modules || []}
        columns={columns}
        actions={actions}
        bulkActions={bulkActions}
        menus={menus}
        enableRowSelection
        getRowId={getRowId}
        paginationInfo={{
          totalItems: data?.modules.length || 0,
          pageSizes: [],
        }}
        components={{
          EmptyState,
        }}
        isLoading={isLoading}
        aria-label={ACMS.i18n('module_index.dataview.label')}
      />
    </div>
  );
};

export default ModuleAdmin;
