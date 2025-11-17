import axiosLib from '../../lib/axios';

export default function dispatchStaticExport(context: Element | Document = document) {
  const resultTplElement = context.querySelector<HTMLScriptElement>('#js-publish_result_tpl');
  if (resultTplElement === null) {
    return;
  }

  const resultTemplateString = resultTplElement.innerHTML;
  const errorTemplateString = context.querySelector<HTMLScriptElement>('#js-publish_error_tpl')?.innerHTML ?? '';
  const progressTemplateString = context.querySelector<HTMLScriptElement>('#js-publish_progress_tpl')?.innerHTML ?? '';
  const removedFilesTemplateString =
    context.querySelector<HTMLScriptElement>('#js-publish_removed_files_tpl')?.innerHTML ?? '';
  const resutlTemplate = window._.template(resultTemplateString);
  const errorTemplate = window._.template(errorTemplateString);
  const removedFilesTemplate = window._.template(removedFilesTemplateString);
  const progressTemplate = window._.template(progressTemplateString);
  const resultOut = context.querySelector<HTMLElement>('#js-result');
  const errorOut = context.querySelector<HTMLElement>('#js-error');
  const removedFilesOut = context.querySelector<HTMLElement>('#js-removed-files');
  const progress = context.querySelector<HTMLDivElement>('#js-publish_progress');
  const progressBar = progress?.querySelector<HTMLDListElement>('.acms-admin-progress-bar');

  let errorCount = 0;
  const interval = setInterval(async () => {
    try {
      const data = new FormData();
      data.append('ACMS_POST_Logger_ProgressJson', 'exec');
      data.append('type', 'publish');
      data.append('bid', ACMS.Config.bid);
      data.append('formToken', window.csrfToken);
      const response = await axiosLib.post(ACMS.Config.root, data);
      const json = response.data;
      const updatedAt = new Date(json.updatedAt).getTime();
      const now = Date.now();
      const timeout = 180 * 1000; // 180秒

      if (now - updatedAt > timeout) {
        // 一定秒数更新されていないなら「停止した可能性あり」と判断
        json.inProcess = 'タイムアウトしました。リロードして処理が完了しているか確認してください。';
        json.percentage = 0;
        json.errorList.push({
          code: null,
          message: 'タイムアウトしました。リロードして処理が完了しているか確認してください。',
          path: '',
        });
        clearInterval(interval);
      }

      if (json.status === 'notfound') {
        throw new Error('notfound');
      }

      errorCount = 0;
      if (Array.isArray(json.processList) && resultOut !== null) {
        resultOut.innerHTML = resutlTemplate(json);
      }
      if (Array.isArray(json.errorList) && errorOut !== null) {
        errorOut.innerHTML = errorTemplate(json);
      }
      if (Array.isArray(json.removedFiles) && removedFilesOut !== null) {
        removedFilesOut.innerHTML = removedFilesTemplate(json);
      }

      if (progressBar != null) {
        progressBar.style.width = `${json.percentage}%`;
        const span = progressBar.querySelector('span');
        if (span !== null) {
          span.innerHTML = progressTemplate(json);
        }
      }
      if (progress !== null) {
        progress.style.display = 'block';
      }
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (e) {
      errorCount++;
    }
    if (errorCount > 3) {
      clearInterval(interval);
      if (progress !== null) {
        progress.style.display = 'none';
      }
      const forceTerminationForm = context.querySelector<HTMLFormElement>('#js-publish_forced_termination');
      if (forceTerminationForm !== null) {
        forceTerminationForm.style.display = 'none';
      }
    }
  }, 800);
}
