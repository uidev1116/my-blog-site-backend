import { useCallback, useRef } from 'react';
import { BlockEditor } from '@features/block-editor/components/BlockEditor';
import createProps from '@features/block-editor/utils/createProps';
import { render } from '../../../../../utils/react';

export default function useBlockEditor(options: Partial<ReturnType<typeof createProps>> = {}) {
  const ref = useRef<HTMLElement>(null);
  const reactRootsRef = useRef<ReturnType<typeof render>[]>([]);

  const mount = useCallback(() => {
    if (!ref.current) {
      return;
    }
    const elements = ref.current.querySelectorAll<HTMLElement>(ACMS.Config.blockEditorMark);
    if (elements.length === 0) {
      return;
    }

    const roots: ReturnType<typeof render>[] = [];
    elements.forEach((element) => {
      const { target } = element.dataset;

      if (target === undefined) {
        throw new Error('Not found data-target attribute!');
      }

      const container = element.querySelector<HTMLElement>(target);

      if (!container) {
        throw new Error('Not found editor container element!');
      }

      const props = createProps(element, options);

      const root = render(<BlockEditor {...props} />, container);
      roots.push(root);
    });
    reactRootsRef.current = roots;
  }, [options]);

  const unmount = useCallback(() => {
    reactRootsRef.current.forEach((root) => {
      root.unmount();
    });
  }, []);

  return {
    ref,
    mount,
    unmount,
  };
}
