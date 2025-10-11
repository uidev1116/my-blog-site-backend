import { useCallback, useRef } from 'react';
import ResizeImage from '../../../../../lib/resize-image/resize-image';

export default function useResizeImage() {
  const resizeImageRef = useRef<ResizeImage | null>(null);
  const ref = useRef<HTMLDivElement>(null);
  const mount = useCallback(() => {
    if (resizeImageRef.current) {
      resizeImageRef.current.destroy();
    }

    if (ref.current) {
      const instance = new ResizeImage(ref.current);
      instance.resize();
      resizeImageRef.current = instance;
    }
  }, []);

  const unmount = useCallback(() => {
    if (resizeImageRef.current) {
      resizeImageRef.current.destroy();
      resizeImageRef.current = null;
    }
  }, []);

  return { ref, mount, unmount };
}
