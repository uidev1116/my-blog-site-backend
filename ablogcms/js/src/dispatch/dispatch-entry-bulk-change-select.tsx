import { lazy, Suspense } from 'react';
import { render } from '../utils/react';
import Spinner from '../components/spinner/spinner';

export default function dispatchEntryBulkChangeSelect(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.entryBulkChangeSelectMark);
  if (elements.length === 0) {
    return;
  }
  const EntryBulkChangeSelectRoot = lazy(
    () => import(/* webpackChunkName: "entry-bulk-change-select" */ '../features/entry/routes/entry-bulk-change-select')
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
        <EntryBulkChangeSelectRoot />
      </Suspense>,
      element
    );
  });
}
