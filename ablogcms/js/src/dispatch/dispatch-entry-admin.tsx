import { lazy, Suspense } from 'react';
import { render } from '../utils/react';
import Spinner from '../components/spinner/spinner';

export default function dispatchEntryAdmin(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.entryAdminMark);
  if (elements.length === 0) {
    return;
  }
  const EntryAdminRoot = lazy(
    () => import(/* webpackChunkName: "entry-admin" */ '../features/entry/routes/entry-admin')
  );

  elements.forEach((element) => {
    render(
      <Suspense
        fallback={
          <div className="acms-admin-position-absolute acms-admin-top-50 acms-admin-left-50 acms-admin-translate-middle">
            <Spinner size={20} />
          </div>
        }
      >
        <EntryAdminRoot />
      </Suspense>,
      element
    );
  });
}
