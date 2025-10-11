import { useMemo } from 'react';
import { Icon } from '@components/icon';
import type { Menu } from '../../../components/dataview/types';
import type { AcmsContext } from '../../../lib/acmsPath/types';
import type { ModuleType } from '../types';

export interface GetMenusContext {
  context: AcmsContext;
  searchParams: URLSearchParams;
}

export interface UseModuleAdminMenusOptions {
  getMenus: (menus: Menu<ModuleType>[], context: GetMenusContext) => Menu<ModuleType>[];
  context: GetMenusContext;
}

export default function useModuleAdminMenus({ getMenus, context }: UseModuleAdminMenusOptions) {
  const menus = useMemo<Menu<ModuleType>[]>(() => {
    const menus: Menu<ModuleType>[] = [
      {
        id: 'create',
        label: ACMS.i18n('module_index.menu.create'),
        linkProps: {
          className: 'acms-admin-btn acms-admin-btn-success',
        },
        getHref: () =>
          ACMS.Library.acmsLink({
            bid: ACMS.Config.bid,
            admin: 'module_edit',
            searchParams: {
              edit: 'insert',
            },
          }),
      },
      {
        id: 'import',
        label: (
          <>
            <Icon name="download" />
            {ACMS.i18n('module_index.menu.import')}
          </>
        ),
        linkProps: {
          className: 'acms-admin-btn acms-admin-btn-text',
        },
        getHref: () =>
          ACMS.Library.acmsLink({
            bid: ACMS.Config.bid,
            admin: 'module_import',
          }),
      },
    ];
    return getMenus(menus, context);
  }, [getMenus, context]);

  return menus;
}
