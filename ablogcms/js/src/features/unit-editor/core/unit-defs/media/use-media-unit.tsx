import { useCallback, useRef } from 'react';
import MediaUnit from '@features/media/components/media-unit/media-unit';
import setupMediaUnit from '@features/media/setup-media-unit';

export default function useMediaUnit(options: Partial<React.ComponentPropsWithoutRef<typeof MediaUnit>> = {}) {
  const ref = useRef<HTMLDivElement>(null);
  const cleanupRef = useRef<ReturnType<typeof setupMediaUnit> | null>(null);

  const mount = useCallback(() => {
    if (ref.current) {
      const cleanup = setupMediaUnit(ref.current, options);
      cleanupRef.current = cleanup;
    }
  }, [options]);

  const unmount = useCallback(() => {
    cleanupRef.current?.();
  }, []);

  return {
    ref,
    mount,
    unmount,
  };
}
