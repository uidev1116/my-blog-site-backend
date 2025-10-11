import { RouterProvider } from 'react-router';
import ModuleAdmin from '../components/module-admin/module-admin';
import { preloadModules } from '../hooks/use-modules-swr';
import Announce from '../../../components/announce/announce';
import createLoader from '../../../lib/react-router/list-view/createLoader';
import createRouter from '../../../lib/react-router/list-view/createRouter';

const loader = createLoader({
  id: 'module-admin',
  admin: 'module_index',
  onLoad: ({ context, url }) => {
    preloadModules(context, url.searchParams);
  },
  ignoredParams: ['rid'],
});

const router = createRouter({
  element: <ModuleAdmin />,
  errorElement: (
    <Announce
      title={ACMS.i18n('module_index.error.unknown.title')}
      message={ACMS.i18n('module_index.error.unknown.message')}
    />
  ),
  loader,
});

const ModuleAdminRoot = () => {
  return <RouterProvider router={router} />;
};

export default ModuleAdminRoot;
