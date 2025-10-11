import { lazy } from 'react';
import createProps from '@features/block-editor/utils/createProps';
import { render } from '../utils/react';

export default async function dispatchBlockEditor(context: Document | Element = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.blockEditorMark);
  if (elements.length === 0) {
    return;
  }
  const BlockEditor = lazy(
    () => import(/* webpackChunkName: "block-editor" */ '../features/block-editor/components/BlockEditor/BlockEditor')
  );

  elements.forEach((element) => {
    const { target } = element.dataset;

    if (target === undefined) {
      throw new Error('Not found data-target attribute!');
    }

    const container = element.querySelector<HTMLElement>(target);

    if (!container) {
      throw new Error('Not found editor container element!');
    }
    if (container.hasChildNodes()) {
      return;
    }
    const props = createProps(element);

    render(<BlockEditor {...props} />, container);
  });
}
