import axiosLib from '../../lib/axios';

export default function dispatchSystemUpdate(context: Element | Document = document) {
  const systemUpdate = context.querySelector<HTMLElement>('#js-systemUpdate');
  const submitForm = context.querySelector<HTMLFormElement>('.js-system-update-submit');
  if (submitForm) {
    submitForm.addEventListener('submit', () => {
      setTimeout(() => {
        window.location.replace(window.location.href);
      }, 5000);
    });
  }
  if (systemUpdate) {
    const template = systemUpdate.querySelector<HTMLScriptElement>('#js-processing-template')?.innerText || '';
    const box = systemUpdate.querySelector<HTMLElement>('#js-processing-box');
    const progress = systemUpdate.querySelector<HTMLElement>('#js-progress');
    if (!box || !template || !progress) {
      return;
    }
    const progressBar = progress.querySelector<HTMLElement>('.acms-admin-progress-bar');
    if (!progressBar) {
      return;
    }
    const progressMessage = progress.querySelector('span');
    let interval: NodeJS.Timeout;

    const check = async () => {
      try {
        const data = new FormData();
        data.append('ACMS_POST_Logger_ProgressJson', 'exec');
        data.append('type', 'update');
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
      } catch (e) {
        clearInterval(interval);
      }
    };
    interval = setInterval(() => {
      check();
    }, 1000);
  }
}
