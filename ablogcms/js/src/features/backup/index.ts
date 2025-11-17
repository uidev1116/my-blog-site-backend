import axiosLib from '../../lib/axios';

const check = async (type: string, element: HTMLElement, interval: NodeJS.Timeout) => {
  const template = element.querySelector<HTMLScriptElement>('.js-processing-template')?.innerText;
  const box = element.querySelector<HTMLElement>('.js-processing-box');
  if (!box || !template) {
    return;
  }
  const progress = element.querySelector<HTMLElement>('.js-progress');
  if (!progress) {
    return;
  }
  const progressBar = progress?.querySelector<HTMLElement>('.acms-admin-progress-bar');

  if (!progressBar) {
    return;
  }
  const progressMessage = progress.querySelector<HTMLSpanElement>('span');

  try {
    const data = new FormData();
    data.append('ACMS_POST_Logger_ProgressJson', 'exec');
    data.append('type', type);
    data.append('formToken', window.csrfToken);
    const response = await axiosLib.post(ACMS.Config.root, data);
    const json = response.data;
    const updatedAt = new Date(json.updatedAt).getTime();
    const now = Date.now();
    const timeout = 180 * 1000; // 180秒

    if (now - updatedAt > timeout) {
      // 一定秒数更新されていないなら「停止した可能性あり」と判断
      json.error = 'タイムアウトしました。リロードして処理が完了しているか確認してください。';
      clearInterval(interval);
    }

    const engine = window._.template(template);
    box.innerHTML = engine(json);

    if (json.processing) {
      progress.style.display = '';
      if (json.error) {
        progressBar.style.width = '100%';
        progressBar.classList.add('acms-admin-progress-bar-danger');
        progressBar.classList.remove('acms-admin-progress-bar-info');
        if (progressMessage) {
          progressMessage.innerHTML = json.error;
        }
      } else {
        progressBar.style.width = `${json.percentage}%`;
        progressBar.classList.add('acms-admin-progress-bar-info');
        progressBar.classList.remove('acms-admin-progress-bar-danger');
        if (progressMessage) {
          progressMessage.innerHTML = json.inProcess;
        }
      }
    } else {
      progress.style.display = 'none';
      clearInterval(interval);
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
  } catch (error) {
    clearInterval(interval);
  }
};

export default function dispatchBackup(context: Element | Document = document) {
  const databaseExport = context.querySelector<HTMLElement>('#js-database-export');
  if (databaseExport) {
    const interval = setInterval(() => {
      check('backup_db', databaseExport, interval);
    }, 1000);
  }
  const archivesExport = context.querySelector<HTMLElement>('#js-archives-export');
  if (archivesExport) {
    const interval2 = setInterval(() => {
      check('backup_archives', archivesExport, interval2);
    }, 1000);
  }
}
