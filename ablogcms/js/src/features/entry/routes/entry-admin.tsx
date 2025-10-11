import { RouterProvider } from 'react-router';
import EntryAdmin from '../components/entry-admin/entry-admin';
import { preloadEntries } from '../hooks/use-entries-swr';
import { preloadColumnConfig } from '../hooks/use-entry-column-config-swr';
import { preloadCustomColumns } from '../hooks/use-entry-custom-columns-swr';
import Announce from '../../../components/announce/announce';
import createLoader from '../../../lib/react-router/list-view/createLoader';
import createRouter from '../../../lib/react-router/list-view/createRouter';

const loader = createLoader({
  id: 'entry-admin',
  admin: 'entry_index',
  onLoad: ({ context, url }) => {
    Promise.all([preloadEntries(context, url.searchParams), preloadColumnConfig(), preloadCustomColumns()]);
  },
});

const router = createRouter({
  element: (
    <EntryAdmin
      getValues={ACMS.Config.entryAdminConfig.getValues}
      getValuesOptions={ACMS.Config.entryAdminConfig.getValuesOptions}
      getActions={ACMS.Config.entryAdminConfig.getActions}
      getBulkActions={ACMS.Config.entryAdminConfig.getBulkActions}
      getMenus={ACMS.Config.entryAdminConfig.getMenus}
      getColumns={ACMS.Config.entryAdminConfig.getColumns}
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

const EntryAdminRoot = () => {
  return <RouterProvider router={router} />;
};

export default EntryAdminRoot;
