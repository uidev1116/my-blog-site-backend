import { useState, useEffect, useCallback, useMemo } from 'react';
import useFirstMountState from './use-first-mount-state';
import useCallbackRef from './use-callback-ref';

interface UseDisclosureProps {
  isOpen?: boolean;
  defaultIsOpen?: boolean;
  closeTimeout?: number;
  onAfterOpen?: () => void;
  onAfterClose?: () => void;
}

interface UseDisclosureReturn {
  isOpen: boolean;
  beforeClose: boolean;
  afterOpen: boolean;
  close: () => void;
  open: () => void;
  toggle: () => void;
}

/**
 * 開閉状態を管理するためのフックです。
 * このフックは、モーダルやアコーディオンなどの開閉状態を管理するために使用します。
 * 開閉状態の変更時にアニメーションを実行するためのプロパティやメソッドを提供します。
 *
 * @example
 * ```tsx
 * const MyComponent = () => {
 *   const { isOpen, beforeClose, afterOpen, close, open } = useDisclosure({
 *     closeTimeout: 300,
 *     onAfterOpen: () => console.log('Opened!'),
 *     onAfterClose: () => console.log('Closed!'),
 *   });
 *
 *   return (
 *     <div>
 *       <button onClick={open}>Open Modal</button>
 *       <button onClick={close}>Close Modal</button>
 *       {isOpen && (
 *         <div className={`modal ${afterOpen ? 'open' : ''} ${beforeClose ? 'closing' : ''}`}>
 *           Modal Content
 *         </div>
 *       )}
 *     </div>
 *   );
 * };
 *
 * ```tsx
 * const Accordion = () => {
 *   const { isOpen, beforeClose, afterOpen, toggle } = useDisclosure({
 *     closeTimeout: 300,
 *     onAfterOpen: () => console.log('Opened!'),
 *     onAfterClose: () => console.log('Closed!'),
 *   });
 *
 *
 *   return (
 *     <div className="accordion-section">
 *       <button onClick={toggle} className="accordion-header">
 *         Open/Close
 *       </button>
 *       <div className={`accordion-content ${afterOpen ? 'open' : ''} ${beforeClose ? 'closing' : ''}`}>
 *         {isOpen && (
 *           <div className="accordion-body">
 *             Accordion Content
 *           </div>
 *         )}
 *       </div>
 *     </div>
 *   );
 * };
 *
 * @param {UseDisclosureProps} props - フックのプロパティ
 * @returns {UseDisclosureReturn} - 管理された状態と操作メソッドを返します
 */
const useDisclosure = ({
  isOpen: controlledIsOpen,
  defaultIsOpen = false,
  closeTimeout = 0,
  onAfterOpen,
  onAfterClose,
}: UseDisclosureProps = {}): UseDisclosureReturn => {
  const isFirstMount = useFirstMountState();
  const [internalState, setInternalState] = useState({
    isOpen: controlledIsOpen ?? defaultIsOpen,
    beforeClose: false,
    afterOpen: false,
  });

  // useCallbackRef でラップすることで、コールバック差し替え時の stale closure を回避する
  const handleAfterOpen = useCallbackRef(onAfterOpen);
  const handleAfterClose = useCallbackRef(onAfterClose);

  useEffect(() => {
    if (controlledIsOpen !== undefined) {
      if (controlledIsOpen) {
        setInternalState((prevState) => ({
          ...prevState,
          isOpen: true,
          beforeClose: false,
        }));
      } else if (!isFirstMount && internalState.isOpen) {
        setInternalState((prevState) => ({
          ...prevState,
          beforeClose: true,
        }));
      }
    }
  }, [controlledIsOpen, isFirstMount, internalState.isOpen]);

  useEffect(() => {
    let animationFrame: number;
    if (internalState.isOpen && !internalState.afterOpen) {
      animationFrame = requestAnimationFrame(() => {
        setInternalState((prevState) => ({
          ...prevState,
          afterOpen: true,
        }));
        handleAfterOpen();
      });
    }
    return () => {
      if (animationFrame) {
        cancelAnimationFrame(animationFrame);
      }
    };
  }, [internalState.isOpen, internalState.afterOpen, handleAfterOpen]);

  useEffect(() => {
    let timeoutId: number | undefined;
    if (internalState.beforeClose) {
      timeoutId = window.setTimeout(() => {
        setInternalState({
          isOpen: false,
          beforeClose: false,
          afterOpen: false,
        });
        handleAfterClose();
      }, closeTimeout);
    }
    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, [internalState.beforeClose, closeTimeout, handleAfterClose]);

  // beforeCloseがtrueの間はisOpenをtrueとすることで、閉じるアニメーションを実行する
  const isOpen = useMemo(
    () => internalState.isOpen || internalState.beforeClose,
    [internalState.isOpen, internalState.beforeClose]
  );

  const open = useCallback(() => {
    // 完全に開いている場合のみ no-op。閉じアニメ中（beforeClose=true）の場合は再開する
    setInternalState((prev) => {
      if (prev.isOpen && !prev.beforeClose) {
        return prev;
      }
      return {
        isOpen: true,
        beforeClose: false,
        afterOpen: false,
      };
    });
  }, []);

  const close = useCallback(() => {
    setInternalState((prev) => {
      if (!prev.isOpen) {
        return prev;
      }
      if (prev.beforeClose) {
        return prev;
      }
      return { ...prev, beforeClose: true };
    });
  }, []);

  const toggle = useCallback(() => {
    setInternalState((prev) => {
      // 完全に開いている → 閉じる
      if (prev.isOpen && !prev.beforeClose) {
        return { ...prev, beforeClose: true };
      }
      // 閉じている or 閉じアニメ中 → 開く（再開）
      return {
        isOpen: true,
        beforeClose: false,
        afterOpen: false,
      };
    });
  }, []);

  return {
    isOpen,
    beforeClose: internalState.beforeClose,
    afterOpen: internalState.afterOpen,
    open,
    close,
    toggle,
  };
};

export default useDisclosure;
