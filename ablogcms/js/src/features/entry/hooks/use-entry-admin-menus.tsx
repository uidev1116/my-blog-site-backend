import { useMemo } from 'react';
import { useNavigate } from 'react-router';
import { Icon } from '@components/icon';
import type { ColumnServiceInterface, Menu } from '../../../components/dataview/types';
import type { EntrySort, EntryType } from '../types';
import type { AcmsContext } from '../../../lib/acmsPath/types';
import EntryCreateForm from '../components/entry-create-form';
import SortCategorySelectModal from '../components/sort-category-select-modal/sort-category-select-modal';
import SortUserSelectModal from '../components/sort-user-select-modal/sort-user-select-modal';
import { createColumnConfigMenu } from '../../../components/dataview/menus';

export interface GetMenusContext {
  context: AcmsContext;
  searchParams: URLSearchParams;
  sort?: EntrySort;
  columnService: ColumnServiceInterface<EntryType>;
}

export interface UseEntryAdminMenusOptions {
  getMenus: (menus: Menu<EntryType>[], context: GetMenusContext) => Menu<EntryType>[];
  context: GetMenusContext;
}

export default function useEntryAdminMenus({ getMenus, context }: UseEntryAdminMenusOptions) {
  const navigate = useNavigate();
  const menus = useMemo<Menu<EntryType>[]>(() => {
    const menus: Menu<EntryType>[] = [
      {
        id: 'clear-sort',
        label: (
          <>
            <Icon name="close" />
            {ACMS.i18n('entry_index.menu.clear_sort_mode')}
          </>
        ),
        buttonProps: {
          className: 'acms-admin-btn acms-admin-btn-info',
        },
        onAction: () => {
          navigate(ACMS.Library.acmsLink({ bid: context.context.bid, admin: 'entry_index' }));
        },
        condition: () => context.sort?.enabled === true,
      },
      {
        id: 'entry-create-menu',
        label: ACMS.i18n('entry_index.menu.entry_create'),
        renderCustomMenu: () => {
          return (
            <EntryCreateForm
              blogId={context.context.bid || parseInt(ACMS.Config.bid, 10)}
              categoryId={context.context.cid}
            />
          );
        },
        condition: () => context.sort?.enabled === false,
      },
      {
        id: 'more-menu',
        label: (
          <>
            {ACMS.i18n('entry_index.menu.more')}
            <Icon name="more_vert" />
          </>
        ),
        buttonProps: {
          className: 'acms-admin-btn acms-admin-btn-text',
        },
        menus: [
          {
            id: 'entry-sort-group',
            title: ACMS.i18n('entry_index.menu.sort_menu_title'),
            menus: [
              {
                id: 'entry-sort',
                label: (
                  <>
                    <Icon name="description" />
                    {ACMS.i18n('entry_index.sort_mode.entry_sort')}
                  </>
                ),
                onAction: () => {
                  navigate(
                    ACMS.Library.acmsLink({ bid: context.context.bid, order: 'sort-asc', admin: 'entry_index' })
                  );
                },
                condition: () => context.sort?.permissions.entry === true,
              },
              {
                id: 'category-sort',
                label: (
                  <>
                    <Icon name="folder" />
                    {ACMS.i18n('entry_index.sort_mode.category_sort')}
                  </>
                ),
                renderModal: ({ isOpen, close }) => {
                  return <SortCategorySelectModal isOpen={isOpen} onClose={close} />;
                },
                condition: () => context.sort?.permissions.category === true,
              },
              {
                id: 'user-sort',
                label: (
                  <>
                    <Icon name="person" />
                    {ACMS.i18n('entry_index.sort_mode.user_sort')}
                  </>
                ),
                ...(context.sort?.permissions.otherUser
                  ? {
                      renderModal: ({ isOpen, close }) => {
                        return <SortUserSelectModal isOpen={isOpen} onClose={close} />;
                      },
                    }
                  : {
                      onAction: () => {
                        navigate(
                          ACMS.Library.acmsLink({
                            bid: context.context.bid,
                            uid: ACMS.Config.suid,
                            order: 'sort-asc',
                            admin: 'entry_index',
                          })
                        );
                      },
                    }),
                condition: () => context.sort?.permissions.user === true,
              },
            ],
          },
          {
            id: 'admin-menu',
            title: ACMS.i18n('entry_index.menu.admin_menu_title'),
            condition: () => ACMS.Config.auth === 'administrator',
            menus: [
              createColumnConfigMenu<EntryType>(context.columnService),
              {
                id: 'import',
                label: (
                  <>
                    <Icon name="download" />
                    {ACMS.i18n('entry_index.menu.import')}
                  </>
                ),
                getHref: () => ACMS.Library.acmsLink({ admin: 'entry_import' }),
              },
              {
                id: 'bulk-change',
                label: (
                  <>
                    <Icon name="published_with_changes" />
                    {ACMS.i18n('entry_index.menu.bulk_update')}
                  </>
                ),
                getHref: () =>
                  ACMS.Library.acmsLink({
                    ...context.context,
                    admin: 'entry_bulk-change',
                    searchParams: context.searchParams,
                  }),
              },
            ],
          },
        ],
      },
    ];
    return getMenus(menus, context);
  }, [context, getMenus, navigate]);

  return menus;
}
