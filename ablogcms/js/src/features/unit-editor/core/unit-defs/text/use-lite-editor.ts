import { useCallback, useRef } from 'react';
import setupLiteEditor from '../../../../../lib/lite-editor';

export default function useLiteEditor() {
  const instancesRef = useRef<Awaited<ReturnType<typeof setupLiteEditor>>>([]);
  const ref = useRef<HTMLDivElement>(null);

  const mount = useCallback(async () => {
    if (!ref.current) {
      return;
    }

    const liteEditors = await setupLiteEditor(ref.current);
    instancesRef.current = liteEditors;
  }, []);

  const unmount = useCallback(() => {
    if (instancesRef.current.length > 0) {
      // TODO: destroy処理を追加（lite-editorにdestroyメソッドがないため現状はできない）
      // instancesRef.current.forEach((liteEditor) => {
      //   liteEditor.destroy();
      // });
      instancesRef.current = [];
    }
  }, []);

  return {
    ref,
    instancesRef,
    mount,
    unmount,
  };
}
