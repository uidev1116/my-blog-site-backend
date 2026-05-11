import { useState, useEffect, useCallback } from 'react';
import Alert from '@components/alert/alert';
import { fetchClient } from '../../lib/fetch-client';

interface ProcessItem {
  status: string;
  message: string;
}

interface ProgressResponse {
  processing: boolean;
  success: boolean;
  error: string;
  percentage: number;
  inProcess: string;
  updatedAt: string;
  processList: ProcessItem[];
}

interface BackgroundProcessProps {
  type: string;
  successMessage: string;
  errorMessage: string;
  interval?: number;
  timeout?: number;
  showProcessList?: boolean;
}

const BackgroundProcess = ({
  type,
  successMessage,
  errorMessage,
  interval = 1_000,
  timeout = 180_000,
  showProcessList = true,
}: BackgroundProcessProps) => {
  const [json, setJson] = useState<ProgressResponse | null>(null);
  const [stopped, setStopped] = useState(false);

  const poll = useCallback(async () => {
    try {
      const data = new FormData();
      data.append('ACMS_POST_Logger_ProgressJson', 'exec');
      data.append('type', type);
      data.append('formToken', window.csrfToken);
      const response = await fetchClient.post<ProgressResponse>(ACMS.Config.root, data);
      const result: ProgressResponse = response.data;
      const updatedAt = new Date(result.updatedAt).getTime();
      const now = Date.now();

      if (now - updatedAt > timeout) {
        result.error = 'タイムアウトしました。リロードして処理が完了しているか確認してください。';
        setStopped(true);
      }

      setJson(result);

      if (!result.processing) {
        setStopped(true);
      }
    } catch {
      setStopped(true);
    }
  }, [type, timeout]);

  useEffect(() => {
    const id = setInterval(() => {
      poll();
    }, interval);

    if (stopped) {
      clearInterval(id);
    }
    return () => clearInterval(id);
  }, [poll, interval, stopped]);

  if (!json) {
    return null;
  }

  const { processing, success, error, percentage, inProcess, processList } = json;

  return (
    <>
      {processing && (
        <div
          className="acms-admin-progress acms-admin-progress-striped acms-admin-active"
          style={{ position: 'relative' }}
        >
          <div
            className={`acms-admin-progress-bar ${error ? 'acms-admin-progress-bar-danger' : 'acms-admin-progress-bar-info'}`}
            style={{ width: `${error ? 100 : percentage}%` }}
          />
          <span
            style={{
              position: 'absolute',
              inset: 0,
              margin: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
            }}
          >
            {error || inProcess}
          </span>
        </div>
      )}

      {!processing && success && <Alert type="info">{successMessage}</Alert>}

      {!processing && error && <Alert type="warning">{errorMessage}</Alert>}

      {showProcessList && processList && processList.length > 0 && (
        <ul>
          {processList.map((item) => (
            // eslint-disable-next-line react/no-array-index-key
            <li key={item.message} className={item.status === 'ng' ? 'acms-admin-text-danger' : undefined}>
              {item.status === 'ng' ? `[Error] ${item.message}` : item.message}
            </li>
          ))}
        </ul>
      )}
    </>
  );
};

export default BackgroundProcess;
