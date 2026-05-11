import { useCallback, useEffect, useRef, forwardRef, useImperativeHandle } from 'react';
import { createPortal } from 'react-dom';

import type { AriaRole, ReactNode, MouseEvent, KeyboardEvent } from 'react';
import { isScrollable } from '../../utils';
import useBodyScrollLock from '../../hooks/use-body-scroll-lock';
import useFocusTrap from '../../hooks/use-focus-trap';
import useAriaHidden from '../../hooks/use-aria-hidden';
import useDisclosure from '../../hooks/use-disclosure';

interface BaseModalProps {
  isOpen: boolean;
  onClose: () => void;
  container?: HTMLElement;
  onAfterOpen?: () => void;
  onAfterClose?: () => void;
  shouldCloseOnBackdropClick?: boolean;
  shouldCloseOnEsc?: boolean;
  id?: string;
  className?: string;
  backdropClassName?: string;
  dialogClassName?: string;
  afterOpenClassName?: string;
  beforeCloseClassName?: string;
  style?: React.CSSProperties;
  backdropStyle?: React.CSSProperties;
  dialogStyle?: React.CSSProperties;
  preventScroll?: boolean;
  closeTimeout?: number;
  role?: AriaRole;
  'aria-modal'?: boolean | 'false' | 'true';
  'aria-labelledby'?: string;
  'aria-describedby'?: string;
  children?: ReactNode;
  focusTrapOptions?: Parameters<typeof useFocusTrap>[0];
}

const EMPTY_STYLE: React.CSSProperties = {};
const EMPTY_FOCUS_TRAP_OPTIONS: Parameters<typeof useFocusTrap>[0] = {};

const BaseModal = (
  {
    isOpen,
    onClose = () => {},
    container = document.body,
    onAfterOpen,
    onAfterClose,
    shouldCloseOnBackdropClick = true,
    shouldCloseOnEsc = true,
    id,
    className = '',
    backdropClassName = '',
    dialogClassName = '',
    style = EMPTY_STYLE,
    backdropStyle = EMPTY_STYLE,
    dialogStyle = EMPTY_STYLE,
    afterOpenClassName = 'is-after-open',
    beforeCloseClassName = 'is-before-close',
    preventScroll = true,
    closeTimeout = 0,
    role = 'dialog',
    'aria-modal': ariaModal = true,
    'aria-describedby': ariaDescribedby,
    'aria-labelledby': ariaLabelledby,
    children,
    focusTrapOptions = EMPTY_FOCUS_TRAP_OPTIONS,
  }: BaseModalProps,
  ref: React.Ref<HTMLDivElement | null>
) => {
  const modalRef = useRef<HTMLDivElement | null>(null);
  const dialogRef = useRef<HTMLDivElement | null>(null);

  const { setRef: setBodyScrollLockRef, disableScroll, enableScroll } = useBodyScrollLock();

  const { setRef: setAriaHiddenRef, hide, unhide } = useAriaHidden();

  const {
    setRef: setTrapRef,
    activate,
    deactivate,
  } = useFocusTrap({
    fallbackFocus() {
      return dialogRef.current as HTMLDivElement;
    },
    ...focusTrapOptions,
    allowOutsideClick: true, // falseの場合、モーダルを閉じてもFocusTrapが解除されないので注意
    escapeDeactivates: false, // ESCキーを押したときの動作は自前でカスタマイズしたいのでfalse
    returnFocusOnDeactivate: true,
  });

  const setModalRefs = useCallback(
    (node: HTMLDivElement | null) => {
      modalRef.current = node;
      setTrapRef(node);
      setAriaHiddenRef(node);
    },
    [setTrapRef, setAriaHiddenRef]
  );

  const setDialogRefs = useCallback(
    (node: HTMLDivElement | null) => {
      dialogRef.current = node;
      setBodyScrollLockRef(node);
    },
    [setBodyScrollLockRef]
  );

  useImperativeHandle(ref, () => modalRef.current);

  const close = useCallback(() => {
    onClose();
  }, [onClose]);

  const disclosureState = useDisclosure({
    isOpen,
    closeTimeout,
    onAfterOpen,
    onAfterClose,
  });

  useEffect(() => {
    if (!disclosureState.isOpen) {
      if (preventScroll) {
        enableScroll();
      }
      deactivate();
      unhide();
    }
  }, [disclosureState.isOpen, preventScroll, enableScroll, deactivate, unhide]);

  useEffect(() => {
    if (disclosureState.afterOpen && disclosureState.isOpen) {
      if (preventScroll) {
        disableScroll({
          allowTouchMove(el) {
            // モーダル内のスクロールを許可する要素かどうかを判定
            let element: Element | HTMLElement | null = el;
            while (element && element !== document.body) {
              if (element !== null && isScrollable(element)) {
                return true;
              }

              element = element.parentElement;
            }
            return false;
          },
        });
      }
      activate();
      hide();
    }
  }, [disclosureState.afterOpen, disclosureState.isOpen, preventScroll, disableScroll, activate, hide]);

  const backdropRef = useRef<HTMLDivElement>(null);

  const handleBackdropClick = useCallback(
    (event: MouseEvent<HTMLDivElement>) => {
      if (shouldCloseOnBackdropClick && event.target === event.currentTarget) {
        close();
      }
    },
    [shouldCloseOnBackdropClick, close]
  );

  const handleKeydown = useCallback(
    (event: KeyboardEvent<HTMLDivElement>) => {
      if (shouldCloseOnEsc && event.code === 'Escape') {
        event.stopPropagation();
        close();
      }
    },
    [shouldCloseOnEsc, close]
  );

  const buildClassName = (): string => {
    const { afterOpen, beforeClose } = disclosureState;
    let result = className;

    if (afterOpen) {
      result = `${className} ${afterOpenClassName}`;
    }

    if (beforeClose) {
      result = `${className} ${beforeCloseClassName}`;
    }

    return result;
  };

  if (!disclosureState.isOpen) {
    return null;
  }

  return createPortal(
    <div ref={setModalRefs} style={style} className={buildClassName()} id={id}>
      <div // eslint-disable-line jsx-a11y/click-events-have-key-events
        ref={backdropRef}
        role="presentation"
        style={backdropStyle}
        className={backdropClassName}
        onClick={handleBackdropClick}
      >
        <div // eslint-disable-line jsx-a11y/no-static-element-interactions
          ref={setDialogRefs}
          tabIndex={-1}
          style={dialogStyle}
          className={dialogClassName}
          role={role}
          aria-modal={ariaModal}
          aria-labelledby={ariaLabelledby}
          aria-describedby={ariaDescribedby}
          onKeyDown={handleKeydown}
        >
          {children}
        </div>
      </div>
    </div>,
    container
  );
};

BaseModal.displayName = 'BaseModal';

export default forwardRef(BaseModal);
