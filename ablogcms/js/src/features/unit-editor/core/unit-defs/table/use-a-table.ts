import { useCallback, useRef } from 'react';
import setupAtable, { type ATableOptions } from '../../../../../lib/a-table';

export default function useATable(options: ATableOptions = {}) {
  const instancesRef = useRef<ReturnType<typeof setupAtable>[]>([]);
  const ref = useRef<HTMLDivElement>(null);
  const mountedRef = useRef(false);

  const mount = useCallback(() => {
    if (!ref.current) {
      return;
    }
    if (mountedRef.current) {
      return;
    }

    const elements = ref.current.querySelectorAll<HTMLElement>('.js-table-unit');
    const aTables = Array.from(elements)
      .filter((element) => {
        if (element.querySelector<HTMLTableElement>('table') !== null) {
          return true;
        }
        return false;
      })
      .map((element) => {
        return setupAtable(element, options);
      });
    instancesRef.current = aTables;
    mountedRef.current = true;
  }, [options]);

  const unmount = useCallback(() => {
    if (instancesRef.current.length > 0) {
      // TODO: destroy処理を追加（a-tableにdestroyメソッドがないため現状はできない）
      // instancesRef.current.forEach((aTable) => {
      //   aTable.destroy();
      // });
      instancesRef.current = [];
    }
    mountedRef.current = false;
  }, []);

  return {
    ref,
    instancesRef,
    mount,
    unmount,
  };
}
