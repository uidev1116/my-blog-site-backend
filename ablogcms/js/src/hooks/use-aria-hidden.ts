import { useCallback, useRef, useEffect } from 'react';
import { hideOthers } from 'aria-hidden';

import type { Undo } from 'aria-hidden';

export interface UseAriaHiddenReturn {
  /**
   * Set callback ref of target element
   */
  setRef: (node: HTMLElement | null) => void;

  /**
   * Set callback ref of parent element
   */
  setParentRef: (node: HTMLElement | null) => void;

  /**
   * Marks everything as aria-hidden
   *
   */
  hide: () => void;

  /**
   * Unmarks everything as aria-hidden
   *
   */
  unhide: () => void;
}

const useAriaHidden = (markerName?: string): UseAriaHiddenReturn => {
  const targetRef = useRef<HTMLElement | null>(null);
  const parentRef = useRef<HTMLElement | undefined>(undefined);
  const undoRef = useRef<Undo | null>(null);

  const hide = useCallback(() => {
    // 既に hide 済みなら、新しい hide を走らせる前に前回分を undo して取り逃しを防ぐ
    if (undoRef.current) {
      undoRef.current();
      undoRef.current = null;
    }
    if (targetRef.current) {
      undoRef.current = hideOthers(targetRef.current, parentRef.current, markerName);
    }
  }, [markerName]);

  const unhide = useCallback(() => {
    if (undoRef.current) {
      undoRef.current();
      undoRef.current = null;
    }
  }, []);

  useEffect(
    () => () => {
      unhide();
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []
  );

  const setRef = useCallback((node: HTMLElement | null) => {
    targetRef.current = node;
  }, []);

  const setParentRef = useCallback((node: HTMLElement | null) => {
    parentRef.current = node ?? undefined;
  }, []);

  return {
    setRef,
    setParentRef,
    hide,
    unhide,
  };
};

export default useAriaHidden;
