import { useState, useCallback, useEffect, useRef } from 'react';
import type { DeviceChangeEvent } from 'react-device-mode';
import { createPortal } from 'react-dom';
import DeviceModeModal from '../../../../components/device-mode/device-mode-modal';
import Splash from '../../../../components/splash/splash';
import useWindowSize from '../../../../hooks/use-window-size';
import useCapture from '../../hooks/use-capture';
import usePreviewMode from '../../hooks/use-preview-mode';
import PreviewShareModal from '../preview-share-modal/preview-share-modal';
import PreviewActions from '../preview-actions/preview-actions';

interface PreviewModalProps {
  buttons: NodeListOf<HTMLButtonElement>;
}

const isClosable = (element: HTMLElement | null) => element?.getAttribute('data-close') === '1';
const isShareable = (element: HTMLElement | null) => element?.getAttribute('data-share') === '1';
const defaultDevice = (element: HTMLElement | null) => element?.getAttribute('data-default-device') || '';
const hasHistoryDevice = (element: HTMLElement | null) => element?.getAttribute('data-has-history-device') === 'on';
const historyDeviceKey = (element: HTMLElement | null) =>
  element?.getAttribute('data-history-device-key') || 'acms-history-device';

const PreviewModal = ({ buttons }: PreviewModalProps) => {
  const deviceModalRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLElement | null>(null);

  const {
    url,
    isLoading,
    enablePreviewMode,
    disablePreviewMode,
    changeUrl,
    setIframeRef: setPreviewIframeRef,
  } = usePreviewMode();
  const { capture, isCapturing, setIframeRef: setCaputureIframeRef } = useCapture();

  const [isOpen, setIsOpen] = useState(false);

  const [windowWidth] = useWindowSize();
  const isNaked = windowWidth < 768;

  const [isShareModalOpen, setIsShareModalOpen] = useState(false);

  const handleDeviceInit = useCallback(
    async (event: DeviceChangeEvent) => {
      await enablePreviewMode(event.device.ua);
    },
    [enablePreviewMode]
  );

  const handleDeviceUpdated = useCallback(
    async (event: DeviceChangeEvent) => {
      await enablePreviewMode(event.device.ua);
    },
    [enablePreviewMode]
  );

  const handleClose = useCallback(async () => {
    await disablePreviewMode();
    setIsOpen(false);
  }, [disablePreviewMode]);

  const handleShare = useCallback(() => {
    setIsShareModalOpen(true);
  }, []);

  const handleCloseShareModal = useCallback(() => {
    setIsShareModalOpen(false);
  }, []);

  const handleCapture = useCallback(async () => {
    await capture();
  }, [capture]);

  const handleMessage = useCallback(
    (e: MessageEvent) => {
      if (e.data.task === 'preview') {
        changeUrl(e.data.url);
      }
    },
    [changeUrl]
  );

  const openPreviewModal = useCallback(
    (element: HTMLElement) => {
      containerRef.current = element;
      setIsOpen(true);
      changeUrl(containerRef.current.getAttribute('data-url') || location.href);
    },
    [changeUrl]
  );

  const handleAfterClose = useCallback(() => {
    if (containerRef.current) {
      document.body.removeChild(containerRef.current);
    }
  }, []);

  useEffect(() => {
    const handleClick = (event: MouseEvent) => {
      const previewArea = document.createElement('div');
      previewArea.id = 'acms-preview-area';

      // event.target の data 属性をすべてコピー
      const target = event.currentTarget as HTMLElement;
      if (target.dataset) {
        Object.keys(target.dataset).forEach((key) => {
          previewArea.dataset[key] = target.dataset[key]!; // eslint-disable-line @typescript-eslint/no-non-null-assertion
        });
      }

      // 作成した div を body に追加
      document.body.insertAdjacentElement('beforeend', previewArea);
      openPreviewModal(document.querySelector('#acms-preview-area')!); // eslint-disable-line @typescript-eslint/no-non-null-assertion
    };

    if (buttons.length > 0) {
      buttons.forEach((button) => {
        button.addEventListener('click', handleClick);
      });
    }

    return () => {
      if (buttons.length > 0) {
        buttons.forEach((button) => {
          button.removeEventListener('click', handleClick);
        });
      }
    };
  }, [buttons, openPreviewModal]);

  return (
    <>
      <DeviceModeModal
        ref={deviceModalRef}
        isOpen={isOpen}
        isNaked={isNaked}
        hasCloseBtn={isClosable(containerRef.current)}
        defaultDevice={defaultDevice(containerRef.current)}
        hasHistoryDevice={hasHistoryDevice(containerRef.current)}
        historyDeviceKey={historyDeviceKey(containerRef.current)}
        isLoading={isLoading}
        onDeviceInit={handleDeviceInit}
        onDeviceUpdated={handleDeviceUpdated}
        onClose={handleClose}
        devices={ACMS.Config.previewDevices}
        onAfterClose={handleAfterClose}
        onMessage={handleMessage}
        src={url}
        getIframe={(iframe) => {
          setPreviewIframeRef(iframe);
          setCaputureIframeRef(iframe);
        }}
        headerRight={
          <PreviewActions
            onCapture={handleCapture}
            onShare={isShareable(containerRef.current) ? handleShare : undefined}
          />
        }
        container={containerRef.current!} // eslint-disable-line @typescript-eslint/no-non-null-assertion
      />
      {deviceModalRef.current && (
        <PreviewShareModal
          isOpen={isShareModalOpen}
          onClose={handleCloseShareModal}
          container={deviceModalRef.current}
          url={url}
        />
      )}
      {isCapturing &&
        createPortal(
          <Splash message={ACMS.i18n('preview.capturing')} />,
          containerRef.current! // eslint-disable-line @typescript-eslint/no-non-null-assertion
        )}
    </>
  );
};

export default PreviewModal;
