import { useRef, useCallback, useEffect, forwardRef, useImperativeHandle } from 'react';
import DeviceMode from './device-mode';
import BaseModal from '../modal/base-modal';

interface DeviceModeModalProps extends React.ComponentPropsWithoutRef<typeof DeviceMode> {
  src: string;
  onMessage?: (e: MessageEvent) => void;
  onAfterOpen?: () => void;
  onAfterClose?: () => void;
  headerLeft?: React.ReactNode;
  headerRight?: React.ReactNode;
  container: HTMLElement;
  isOpen?: boolean;
  focusTrapOptions?: React.ComponentPropsWithoutRef<typeof BaseModal>['focusTrapOptions'];
}

const EMPTY_FOCUS_TRAP_OPTIONS: NonNullable<React.ComponentPropsWithoutRef<typeof BaseModal>['focusTrapOptions']> = {};

const DeviceModeModal = (
  {
    onClose = () => {},
    onMessage = () => {},
    onAfterOpen = () => {},
    onAfterClose = () => {},
    container,
    isOpen = false,
    focusTrapOptions = EMPTY_FOCUS_TRAP_OPTIONS,
    ...deveiceModeProps
  }: DeviceModeModalProps,
  ref: React.Ref<HTMLDivElement>
) => {
  const modalRef = useRef<HTMLDivElement | null>(null);

  useImperativeHandle(ref, () => modalRef.current as HTMLDivElement);

  useEffect(() => {
    const handleMessage = (event: MessageEvent) => {
      onMessage(event);
    };
    window.addEventListener('message', handleMessage);
    return () => {
      window.removeEventListener('message', handleMessage);
    };
  }, [onMessage]);

  const handleClose = useCallback(() => {
    onClose();
  }, [onClose]);

  const handleAfterOpen = useCallback(() => {
    onAfterOpen();
  }, [onAfterOpen]);

  const handleAfterClose = useCallback(() => {
    onAfterClose();
  }, [onAfterClose]);

  return (
    <BaseModal
      ref={modalRef}
      closeTimeout={300}
      isOpen={isOpen}
      onClose={handleClose}
      className="acms-admin-device-mode-modal"
      backdropStyle={{ height: '100%' }}
      dialogStyle={{ height: '100%' }}
      onAfterOpen={handleAfterOpen}
      onAfterClose={handleAfterClose}
      container={container}
      focusTrapOptions={focusTrapOptions}
    >
      <DeviceMode onClose={handleClose} {...deveiceModeProps} />
    </BaseModal>
  );
};

DeviceModeModal.displayName = 'DeviceModeModal';

export default forwardRef<HTMLDivElement, DeviceModeModalProps>(DeviceModeModal);
