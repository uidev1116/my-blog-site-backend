import { useState, useEffect, useCallback } from 'react';
import { fetchClient } from '../../lib/fetch-client';

interface ProcessItem {
  message: string;
}

interface ErrorItem {
  code: number | null;
  message: string;
  path: string;
}

interface RemovedFile {
  path: string;
}

interface ProgressResponse {
  processing: boolean;
  status: string;
  percentage: number;
  inProcess: string;
  count: number;
  max: number;
  updatedAt: string;
  processList: ProcessItem[];
  errorList: ErrorItem[];
  removedFiles: RemovedFile[];
}

interface StaticExportProgressProps {
  resultHeading: string;
  removedFilesHeading: string;
  errorHeading: string;
}

const TIMEOUT = 180_000;
const POLL_INTERVAL = 800;
const MAX_ERROR_COUNT = 3;

const StaticExportProgress = ({ resultHeading, removedFilesHeading, errorHeading }: StaticExportProgressProps) => {
  const [json, setJson] = useState<ProgressResponse | null>(null);
  const [stopped, setStopped] = useState(false);
  const [errorCount, setErrorCount] = useState(0);

  const poll = useCallback(async () => {
    try {
      const data = new FormData();
      data.append('ACMS_POST_Logger_ProgressJson', 'exec');
      data.append('type', 'publish');
      data.append('bid', ACMS.Config.bid);
      data.append('formToken', window.csrfToken);
      const response = await fetchClient.post<ProgressResponse>(ACMS.Config.root, data);
      const result: ProgressResponse = response.data;

      if (result.status === 'notfound') {
        throw new Error('notfound');
      }

      const updatedAt = new Date(result.updatedAt).getTime();
      const now = Date.now();

      if (now - updatedAt > TIMEOUT) {
        result.inProcess = 'タイムアウトしました。リロードして処理が完了しているか確認してください。';
        result.percentage = 0;
        result.errorList.push({
          code: null,
          message: 'タイムアウトしました。リロードして処理が完了しているか確認してください。',
          path: '',
        });
        setStopped(true);
      }

      setErrorCount(0);
      setJson(result);
    } catch {
      setErrorCount((prev) => {
        const next = prev + 1;
        if (next > MAX_ERROR_COUNT) {
          setStopped(true);
        }
        return next;
      });
    }
  }, []);

  useEffect(() => {
    const id = setInterval(() => {
      poll();
    }, POLL_INTERVAL);

    if (stopped) {
      clearInterval(id);
    }
    return () => clearInterval(id);
  }, [poll, stopped]);

  useEffect(() => {
    if (errorCount > MAX_ERROR_COUNT) {
      const form = document.getElementById('js-publish_forced_termination');
      if (form) {
        form.style.display = 'none';
      }
    }
  }, [errorCount]);

  if (!json) {
    return null;
  }

  const { percentage, inProcess, count, max, processList, errorList, removedFiles } = json;
  const showProgress = errorCount <= MAX_ERROR_COUNT;

  return (
    <>
      {showProgress && (
        <div
          className="acms-admin-progress acms-admin-progress-striped acms-admin-active"
          style={{ position: 'relative' }}
        >
          <div className="acms-admin-progress-bar acms-admin-progress-bar-info" style={{ width: `${percentage}%` }} />
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
            {inProcess} {percentage}% ( {count}/{max} )
          </span>
        </div>
      )}

      {Array.isArray(processList) && processList.length > 0 && (
        <>
          <h3>{resultHeading}</h3>
          <ul>
            {processList.map((item) => (
              <li key={item.message}>{item.message}</li>
            ))}
          </ul>
        </>
      )}

      {Array.isArray(removedFiles) && removedFiles.length > 0 && (
        <>
          <h3>{removedFilesHeading}</h3>
          <ul>
            {removedFiles.map((file) => (
              <li key={file.path}>{file.path}</li>
            ))}
          </ul>
        </>
      )}

      {Array.isArray(errorList) && errorList.length > 0 && (
        <>
          <h3>{errorHeading}</h3>
          <ul className="acms-admin-text-danger">
            {[...errorList].reverse().map((err, index) => (
              // eslint-disable-next-line react/no-array-index-key
              <li key={index}>
                <span>{err.message} </span>
                {err.code !== null && <span>ステータスコード [{err.code}] </span>}
                {err.path !== '' && <span>({err.path})</span>}
              </li>
            ))}
          </ul>
        </>
      )}
    </>
  );
};

export default StaticExportProgress;
