import { lazy, Suspense } from 'react';
import Spinner from '@components/spinner/spinner';
import { render } from '../../utils/react';

export default function dispatchMediaAdmin(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.mediaAdminMark);
  if (elements.length === 0) {
    return;
  }
  const MediaAdmin = lazy(
    () => import(/* webpackChunkName: "media-admin" */ '../../features/media/components/media-admin/media-admin')
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
        <MediaAdmin />
      </Suspense>,
      element
    );
  });
}
