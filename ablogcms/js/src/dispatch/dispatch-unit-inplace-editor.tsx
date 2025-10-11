import { lazy, Suspense } from 'react';
import Spinner from '@components/spinner/spinner';
import { render } from '../utils/react';
import { getDefaultValue, getSettings } from './dispatch-unit-editor';

export default function dispatchUnitInplaceEditor(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.unitInplaceEditorMark);
  if (elements.length === 0) {
    return;
  }
  const InplaceEditor = lazy(
    () =>
      import(
        /* webpackChunkName: "unit-inplace-editor" */ '../features/unit-editor/components/inplace-editor/inplace-editor'
      )
  );

  elements.forEach((element) => {
    const defaultValue = getDefaultValue(element);
    const settings = getSettings(element);
    const unitId = element.getAttribute('data-unit-id');
    render(
      <Suspense
        fallback={
          <div className="acms-admin-d-flex acms-admin-justify-content-center acms-admin-align-items-center">
            <Spinner size={20} />
          </div>
        }
      >
        <InplaceEditor defaultValue={defaultValue} settings={settings} unitId={unitId || ''} />
      </Suspense>,
      element
    );
  });
}
