import { useCallback, useRef } from 'react';
import useEffectOnce from '../../../../../hooks/use-effect-once';

export default function useWysiwygEditor() {
  const ref = useRef<HTMLElement>(null);

  const mount = useCallback(() => {
    if (ref.current === null) {
      return;
    }

    const textareas = ref.current.querySelectorAll<HTMLTextAreaElement>('textarea');
    textareas.forEach((textarea) => {
      if (!ACMS.Dispatch.wysiwyg.isAdapted(textarea)) {
        ACMS.Dispatch.wysiwyg.init(textarea);
      }
    });
  }, []);

  const unmount = useCallback(() => {
    if (ref.current === null) {
      return;
    }

    const textareas = ref.current.querySelectorAll<HTMLTextAreaElement>('textarea');
    textareas.forEach((textarea) => {
      if (ACMS.Dispatch.wysiwyg.isAdapted(textarea)) {
        ACMS.Dispatch.wysiwyg.destroy(textarea);
      }
    });
  }, []);

  useEffectOnce(() => {
    unmount();
  });

  return { ref, mount, unmount };
}
