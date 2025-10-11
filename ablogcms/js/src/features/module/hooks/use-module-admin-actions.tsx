import { useMemo } from 'react';
import type { Action } from '../../../components/dataview/types';
import type { ModuleType } from '../types';

export interface GetActionsContext {
  invalidate: () => void;
  searchParams: URLSearchParams;
}

export interface UseModuleAdminActionsOptions {
  getActions: (actions: Action<ModuleType>[], context: GetActionsContext) => Action<ModuleType>[];
  context: GetActionsContext;
}

export default function useModuleAdminActions({ getActions, context }: UseModuleAdminActionsOptions) {
  const actions = useMemo<Action<ModuleType>[]>(() => {
    const actions: Action<ModuleType>[] = [
      {
        id: 'edit',
        label: ACMS.i18n('module_index.action.edit'),
        getHref: (data) =>
          ACMS.Library.acmsLink({
            bid: data.blog_id,
            admin: 'module_edit',
            searchParams: new URLSearchParams({
              mid: data.id.toString(),
              edit: 'update',
              ...(context.searchParams.has('rid') && { rid: context.searchParams.get('rid') as string }),
            }),
          }),
        type: 'primary',
        linkProps: {
          className: 'acms-admin-btn-admin',
        },
        condition: (data) => data.actions.includes('edit'),
      },
    ];
    return getActions(actions, context);
  }, [getActions, context]);

  return actions;
}
