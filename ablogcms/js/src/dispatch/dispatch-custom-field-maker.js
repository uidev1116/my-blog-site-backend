import { lazy, Suspense } from 'react';
import Spinner from '@components/spinner/spinner';
import { render } from '../utils/react';

export default function dispatchCustomFieldMaker(context = document) {
  const element = context.querySelector('#custom-field-maker');
  if (!element) {
    return;
  }

  import(/* webpackChunkName: "custom-field-maker-css" */ 'custom-field-maker/lib/assets/custom-field-maker.css');
  const CustomFieldMaker = lazy(() => import(/* webpackChunkName: "custom-field-maker" */ 'custom-field-maker'));
  render(
    <Suspense
      fallback={
        <div className="acms-admin-position-absolute acms-admin-top-50 acms-admin-left-50 acms-admin-translate-middle">
          <Spinner size={20} />
        </div>
      }
    >
      <CustomFieldMaker />
    </Suspense>,
    element
  );
}
