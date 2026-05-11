import { useState, useCallback, useRef, useEffect } from 'react';
import { createFocusTrap } from 'focus-trap';

import type { ActivateOptions, DeactivateOptions, FocusTrap, Options } from 'focus-trap';

export interface UseFocusTrapReturn {
  /**
   * Indicates if the focus trap is currently active
   */
  isActive: boolean;

  /**
   * Indicates if the focus trap is currently paused
   */
  isPaused: boolean;

  /**
   * set callback ref
   */
  setRef: (node: HTMLElement | null) => void;

  /**
   * Activate the focus trap
   *
   * @see https://github.com/focus-trap/focus-trap#trapactivateactivateoptions
   * @param opts Activate focus trap options
   */
  activate: (opts?: ActivateOptions) => void;

  /**
   * Deactivate the focus trap
   *
   * @see https://github.com/focus-trap/focus-trap#trapdeactivatedeactivateoptions
   * @param opts Deactivate focus trap options
   */
  deactivate: (opts?: DeactivateOptions) => void;

  /**
   * Pause the focus trap
   *
   * @see https://github.com/focus-trap/focus-trap#trappause
   */
  pause: () => void;

  /**
   * Unpauses the focus trap
   *
   * @see https://github.com/focus-trap/focus-trap#trapunpause
   */
  unpause: () => void;
}

const useFocusTrap = (options: Options = {}): UseFocusTrapReturn => {
  const trapRef = useRef<FocusTrap | null>(null);

  // options を最新参照で保持し、setRef の deps から外して安定化させる
  const optionsRef = useRef(options);
  optionsRef.current = options;

  const [isActive, setIsActive] = useState(false);
  const [isPaused, setIsPaused] = useState(false);

  const activate = useCallback((opts?: ActivateOptions) => {
    trapRef.current?.activate(opts);
  }, []);

  const deactivate = useCallback((opts?: DeactivateOptions) => {
    trapRef.current?.deactivate(opts);
  }, []);

  const pause = useCallback(() => {
    if (trapRef.current) {
      trapRef.current.pause();
      setIsPaused(true);
    }
  }, []);

  const unpause = useCallback(() => {
    if (trapRef.current) {
      trapRef.current.unpause();
      setIsPaused(false);
    }
  }, []);

  const setRef = useCallback((node: HTMLElement | null) => {
    if (node === null) {
      // 要素がアンマウントされたタイミングで trap を破棄する
      if (trapRef.current) {
        trapRef.current.deactivate();
        trapRef.current = null;
      }
      return;
    }

    if (trapRef.current) {
      trapRef.current.updateContainerElements(node);
      return;
    }

    trapRef.current = createFocusTrap(node, {
      ...optionsRef.current,
      onActivate() {
        setIsActive(true);
        optionsRef.current.onActivate?.();
      },
      onDeactivate() {
        setIsActive(false);
        optionsRef.current.onDeactivate?.();
      },
    });
  }, []);

  useEffect(
    () => () => {
      deactivate();
    },
    [deactivate]
  );

  return {
    isActive,
    isPaused,
    setRef,
    activate,
    deactivate,
    pause,
    unpause,
  };
};

export default useFocusTrap;
