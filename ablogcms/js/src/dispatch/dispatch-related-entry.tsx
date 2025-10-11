import { lazy, Suspense } from 'react';
import { render } from '../utils/react';
import { RelatedEntryType } from '../features/related-entry/types';
import { triggerEvent } from '../utils';

const selector = '.js-related-entry';
const readyClassName = 'js-related-entry-ready';

const dispatchRelatedEntry = (context: Document | Element = document) => {
  const elements = context.querySelectorAll<HTMLElement>(selector);
  if (!elements.length) {
    return;
  }
  const RelatedEntries = lazy(
    () =>
      import(
        /* webpackChunkName: "related-entries" */ '../features/related-entry/components/related-entries/related-entries'
      )
  );
  [].forEach.call(elements, (element: HTMLElement) => {
    if (element.classList.contains(readyClassName)) {
      return;
    }
    element.classList.add(readyClassName);
    const type = element.getAttribute('data-type') ?? '';
    const title = element.getAttribute('data-title') ?? '';
    const moduleId = element.getAttribute('data-module-id') ?? '';
    const ctx = element.getAttribute('data-ctx') ?? '';
    const thumbnail = element.getAttribute('data-thumbnail') ?? '';
    const maxItem = parseInt(element.getAttribute('data-max-item') ?? '0', 10);
    const jsonId = element.getAttribute('data-json-id') ?? '';

    if (!jsonId) {
      throw new Error('Not Found data-json-id attribute.');
    }

    const json = document.getElementById(jsonId)?.innerHTML || '[]';

    const entries: RelatedEntryType[] = JSON.parse(json);
    render(
      <Suspense fallback={null}>
        <RelatedEntries
          entries={entries}
          type={type}
          title={title}
          moduleId={moduleId}
          ctx={ctx}
          thumbnail={thumbnail}
          maxItem={maxItem}
          onChange={(entries) => {
            triggerEvent(element, 'acmsAdminRelatedEntryChange', {
              bubbles: true,
              detail: { entries, type, title, moduleId, ctx, maxItem },
            });
          }}
        />
      </Suspense>,
      element
    );
  });
};

export default dispatchRelatedEntry;
