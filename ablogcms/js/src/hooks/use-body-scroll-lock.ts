import { useCallback, useRef, useEffect } from 'react';
import { disableBodyScroll, enableBodyScroll } from 'body-scroll-lock';

import type { BodyScrollOptions } from 'body-scroll-lock';

export interface UseBodyScrollLockReturn {
  /**
   * set callback ref
   */
  setRef: (node: HTMLElement | null) => void;

  /**
   * disable body element scroll
   *
   * @see https://github.com/willmcpo/body-scroll-lock#options
   * @param opts Body scroll options
   */
  disableScroll: (opts?: BodyScrollOptions) => void;

  /**
   * enable body element scroll
   *
   */
  enableScroll: () => void;
}

const useBodyScrollLock = (): UseBodyScrollLockReturn => {
  const targetRef = useRef<HTMLElement | null>(null);

  const disableScroll = useCallback((opts?: BodyScrollOptions) => {
    if (targetRef.current) {
      disableBodyScroll(targetRef.current, opts);
    }
  }, []);

  const enableScroll = useCallback(() => {
    if (targetRef.current) {
      enableBodyScroll(targetRef.current);
    }
  }, []);

  useEffect(() => {
    return () => {
      enableScroll();
    };
    // 確実にunmount時のみ実行されるようにする
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const setRef = useCallback((node: HTMLElement | null) => {
    if (node === null) {
      return;
    }
    targetRef.current = node;
  }, []);

  return {
    setRef,
    disableScroll,
    enableScroll,
  };
};

export default useBodyScrollLock;
