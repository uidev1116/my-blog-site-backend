import { useState, useCallback, useEffect, useRef } from 'react';
import dayjs from 'dayjs';
import { DeviceChangeEvent } from 'react-device-mode';
import { createPortal } from 'react-dom';
import DeviceModeModal from '../../../../components/device-mode/device-mode-modal';
import Splash from '../../../../components/splash/splash';
import * as api from '../../api';
import PreviewActions from '../../../preview/components/preview-actions/preview-actions';
import { rewriteUrl } from '../../../preview/utils';
import useCapture from '../../../preview/hooks/use-capture';
import useTimeMachineRulesSWR from '../../hooks/use-timemachile-rules-swr';
import { reloadIframe } from '../../../../utils';
import { TimeMachineState } from '../../types';
import TimeMachineForm from '../timemachine-form/timemachine-form';

interface TimeMachineModalProps {
  buttons: NodeListOf<HTMLButtonElement>;
}

const TimeMachineModal = ({ buttons }: TimeMachineModalProps) => {
  const now = dayjs();
  const deviceModalRef = useRef<HTMLDivElement>(null);
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const containerRef = useRef<HTMLElement | null>(null);

  const [isPending, setIsPending] = useState(false);
  const [url, setUrl] = useState(() => rewriteUrl(location.href));
  const { capture, isCapturing, setIframeRef: setCaputureIframeRef } = useCapture();

  const handleCapture = useCallback(async () => {
    await capture();
  }, [capture]);

  const [isOpen, setIsOpen] = useState(false);

  const { rules, isLoading: isRuleLoading } = useTimeMachineRulesSWR(isOpen);

  const [ua, setUa] = useState('');
  const [timeMachineState, setTimeMachineState] = useState<TimeMachineState>({
    date: now.format('YYYY-MM-DD'),
    time: now.format('HH:mm:ss'),
    ruleId: parseInt(ACMS.Config.rid, 10) || 0,
  });

  const enableTimeMachineMode = useCallback(async (ua: string, state: TimeMachineState) => {
    const formData = new FormData();
    formData.append('date', state.date);
    formData.append('time', state.time);
    formData.append('rule', `${state.ruleId}`);
    formData.append('preview_fake_ua', ua);
    formData.append('preview_token', window.csrfToken);
    await api.enableTimeMachineMode(formData);
    if (iframeRef.current) {
      // 時間やルールが変更された場合はiframeをリロードしないと反映されない
      await reloadIframe(iframeRef.current);
    }
  }, []);

  const handleDeviceInit = useCallback(
    async (event: DeviceChangeEvent) => {
      setIsPending(true);
      try {
        await enableTimeMachineMode(event.device.ua, timeMachineState);
      } catch (error) {
        // Todo: エラーハンドリング
        console.error(error); // eslint-disable-line no-console
      } finally {
        setIsPending(false);
        setUa(event.device.ua);
      }
    },
    [enableTimeMachineMode, timeMachineState]
  );

  const handleDeviceUpdated = useCallback(
    async (event: DeviceChangeEvent) => {
      setIsPending(true);
      try {
        await enableTimeMachineMode(event.device.ua, timeMachineState);
      } catch (error) {
        // Todo: エラーハンドリング
        console.error(error); // eslint-disable-line no-console
      } finally {
        setIsPending(false);
        setUa(event.device.ua);
      }
    },
    [enableTimeMachineMode, timeMachineState]
  );

  const handleClose = useCallback(async () => {
    try {
      await api.disableTimeMachineMode();
      setIsOpen(false);
    } catch (error) {
      // Todo: エラーハンドリング
      console.error(error); // eslint-disable-line no-console
    }
  }, []);

  const handleStateChange = useCallback((state: TimeMachineState) => {
    setTimeMachineState(state);
  }, []);

  const handleSubmit = useCallback(async () => {
    setIsPending(true);
    try {
      await enableTimeMachineMode(ua, timeMachineState);
    } catch (error) {
      // Todo: エラーハンドリング
      console.error(error); // eslint-disable-line no-console
    } finally {
      setIsPending(false);
    }
  }, [enableTimeMachineMode, ua, timeMachineState]);

  const handleMessage = useCallback((e: MessageEvent) => {
    if (e.data.task === 'preview') {
      setUrl(rewriteUrl(e.data.url));
    }
  }, []);

  const openTimeMachineModal = useCallback(() => {
    setIsOpen(true);
  }, []);

  useEffect(() => {
    const element = document.createElement('div');
    element.id = 'acms-timemachine-area';
    containerRef.current = element;

    // 作成した div を body に追加
    document.body.insertAdjacentElement('beforeend', element);

    const handleClick = () => {
      openTimeMachineModal();
    };

    if (buttons.length > 0) {
      buttons.forEach((button) => {
        button.addEventListener('click', handleClick);
      });
    }

    if (ACMS.Config.timeMachineMode) {
      openTimeMachineModal();
    }

    return () => {
      if (buttons.length > 0) {
        buttons.forEach((button) => {
          button.removeEventListener('click', handleClick);
        });
      }
      if (containerRef.current) {
        document.body.removeChild(containerRef.current);
      }
    };
  }, [buttons, openTimeMachineModal]);

  return (
    <>
      <DeviceModeModal
        ref={deviceModalRef}
        isOpen={isOpen}
        hasCloseBtn
        devices={ACMS.Config.previewDevices}
        defaultDevice={ACMS.Config.timemachinePreviewDefaultDevice}
        hasHistoryDevice={ACMS.Config.timemachinePreviewHasHistoryDevice === 'on'}
        historyDeviceKey={ACMS.Config.previewDeviceHistoryKey.timemachine}
        isLoading={isRuleLoading || isPending}
        onDeviceInit={handleDeviceInit}
        onClose={handleClose}
        onMessage={handleMessage}
        onDeviceUpdated={handleDeviceUpdated}
        src={url}
        headerLeft={
          <TimeMachineForm
            state={timeMachineState}
            rules={rules}
            onChange={handleStateChange}
            onSubmit={handleSubmit}
          />
        }
        headerRight={<PreviewActions onCapture={handleCapture} />}
        container={containerRef.current!} // eslint-disable-line @typescript-eslint/no-non-null-assertion
        focusTrapOptions={{ initialFocus: false }}
        getIframe={(iframe) => {
          iframeRef.current = iframe;
          setCaputureIframeRef(iframe);
        }}
      />
      {isCapturing &&
        createPortal(
          <Splash message={ACMS.i18n('preview.capturing')} />,
          containerRef.current! // eslint-disable-line @typescript-eslint/no-non-null-assertion
        )}
    </>
  );
};

export default TimeMachineModal;
