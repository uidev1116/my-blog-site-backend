import { lazy, Suspense } from 'react';

import { UnitConfigEditorSettings } from '@features/unit-editor/components/config-editor/types';
import { defaultConfigEditorSettings } from '@features/unit-editor/config';
import { UnitConfigList } from '@features/unit-editor/core/types';
import { render } from '../utils/react';

function getSettings(element: HTMLElement): UnitConfigEditorSettings {
  const json = element.getAttribute('data-settings') ?? '{}';
  const settings: Partial<UnitConfigEditorSettings> = JSON.parse(json);
  return { ...defaultConfigEditorSettings, ...settings };
}

function getDefaultValue(element: HTMLElement): UnitConfigList {
  const json = element.getAttribute('data-default-value') ?? '[]';
  const defaultValue: UnitConfigList = JSON.parse(json);
  return defaultValue;
}

export default function dispatchUnitConfigEditor(context: Element | Document = document) {
  const selector = '#js-unit-config-editor';
  const elements = context.querySelectorAll<HTMLElement>(selector);
  if (elements.length === 0) return;

  const UnitConfigEditor = lazy(
    () =>
      import(
        /* webpackChunkName: "unit-config-editor" */ '../features/unit-editor/components/config-editor/unit-config-editor'
      )
  );

  elements.forEach((element) => {
    const id = element.getAttribute('data-id');
    if (!id) {
      throw new Error('data-id is required');
    }
    const label = element.getAttribute('data-label') ?? '';
    const settings = getSettings(element);
    const defaultValue = getDefaultValue(element);
    render(
      <Suspense fallback={null}>
        <UnitConfigEditor id={id} label={label} defaultValue={defaultValue} settings={settings} />
      </Suspense>,
      element
    );
  });
}
