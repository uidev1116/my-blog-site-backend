import { useCallback, useEffect } from 'react';
import ReactDeviceMode from 'react-device-mode';
import type { DeviceChangeEvent, DeviceType } from 'react-device-mode';

const EMPTY_DEVICES: DeviceType[] = [];

interface DeviceModeProps
  extends Partial<
    Pick<
      React.ComponentPropsWithoutRef<typeof ReactDeviceMode>,
      | 'isNaked'
      | 'hasCloseBtn'
      | 'defaultDevice'
      | 'hasHistoryDevice'
      | 'onClose'
      | 'onDeviceUpdated'
      | 'historyDeviceKey'
      | 'isLoading'
      | 'getIframe'
      | 'onDeviceInit'
      | 'devices'
      | 'headerLeft'
      | 'headerRight'
    >
  > {
  src: string;
  onMessage?: (e: MessageEvent) => void;
}

const DeviceMode = ({
  isNaked = false,
  hasCloseBtn = true,
  defaultDevice = '',
  hasHistoryDevice = true,
  historyDeviceKey = 'acms-history-device',
  onClose = () => {},
  src,
  isLoading,
  onDeviceUpdated = () => {},
  onDeviceInit = () => {},
  onMessage = () => {},
  headerLeft,
  headerRight,
  devices = EMPTY_DEVICES,
  getIframe = () => {},
}: DeviceModeProps) => {
  const handleDeviceInit = useCallback(
    (event: DeviceChangeEvent) => {
      onDeviceInit(event);
    },
    [onDeviceInit]
  );

  const handleDeviceUpdated = useCallback(
    (event: DeviceChangeEvent) => {
      onDeviceUpdated(event);
    },
    [onDeviceUpdated]
  );

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

  return (
    <ReactDeviceMode
      isNaked={isNaked}
      src={src}
      i18n={{ fitWindow: ACMS.i18n('preview.fit_to_screen') }}
      headerLeft={headerLeft}
      headerRight={headerRight}
      onClose={handleClose}
      devices={devices}
      defaultDevice={defaultDevice}
      hasHistoryDevice={hasHistoryDevice}
      historyDeviceKey={historyDeviceKey}
      getIframe={getIframe}
      onDeviceInit={handleDeviceInit}
      onDeviceUpdated={handleDeviceUpdated}
      hasCloseBtn={hasCloseBtn}
      isLoading={isLoading}
    />
  );
};

export default DeviceMode;
