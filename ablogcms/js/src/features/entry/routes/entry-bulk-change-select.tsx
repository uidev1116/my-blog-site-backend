import { RouteObject, RouterProvider } from 'react-router';
import EntryAdmin from '../components/entry-admin/entry-admin';
import { preloadEntries } from '../hooks/use-entries-swr';
import { preloadColumnConfig } from '../hooks/use-entry-column-config-swr';
import { preloadCustomColumns } from '../hooks/use-entry-custom-columns-swr';
import Announce from '../../../components/announce/announce';
import createRouter from '../../../lib/react-router/list-view/createRouter';

const loader: RouteObject['loader'] = ({ params, request }) => {
  const path = params['*'] as string;
  const url = new URL(request.url);
  const context = ACMS.Library.parseAcmsPath(decodeURI(path));
  Promise.all([preloadEntries(context, url.searchParams), preloadColumnConfig(), preloadCustomColumns()]);

  return null;
};

const router = createRouter({
  element: (
    <EntryAdmin
      getValues={ACMS.Config.getValues}
      getValuesOptions={ACMS.Config.getValuesOptions}
      getActions={() => []} // 行のアクションは不要
      getBulkActions={() => []} // 一括操作のアクションは不要
      getMenus={() => []} // メニューは不要
      getColumns={ACMS.Config.getColumns}
      enableRowSelection
      enableRowDnD={false}
      enablePagination={false}
      getRowId={(row) => row.id.toString()}
      components={{
        DataviewActions: ({ className, menus, data, table, components }) => {
          const selectedData = table.getSelectedRowModel().rows.map((row) => row.original);
          return (
            <div className={className}>
              <div>
                <form id="bulk-action-form" method="post" encType="multipart/form-data">
                  <button
                    type="submit"
                    name="ACMS_POST_Entry_BulkChange_Select"
                    className="acms-admin-btn-admin acms-admin-btn-admin-primary"
                    disabled={selectedData.length === 0}
                  >
                    {ACMS.i18n('entry_bulk_change.select.label')}
                  </button>
                  <input type="hidden" name="formToken" value={window.csrfToken} />
                </form>
              </div>
              <components.DataviewMenu menus={menus} data={data} table={table} components={components} />
            </div>
          );
        },
      }}
    />
  ),
  errorElement: (
    <Announce
      title={ACMS.i18n('entry_index.error.unknown.title')}
      message={ACMS.i18n('entry_index.error.unknown.message')}
    />
  ),
  loader,
});

const EntryBulkChangeSelectRoot = () => {
  return <RouterProvider router={router} />;
};

export default EntryBulkChangeSelectRoot;
