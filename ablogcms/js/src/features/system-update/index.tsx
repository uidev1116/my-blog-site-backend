import { render } from '../../utils/react';
import BackgroundProcess from '../background-process/background-process';

export default function dispatchSystemUpdate(context: Element | Document = document) {
  const submitForm = context.querySelector<HTMLFormElement>('.js-system-update-submit');
  if (submitForm) {
    submitForm.addEventListener('submit', () => {
      setTimeout(() => {
        window.location.replace(window.location.href);
      }, 5000);
    });
  }

  const element = context.querySelector<HTMLElement>('#js-systemUpdate');
  if (!element) {
    return;
  }
  const type = element.dataset.type || 'update';
  const successMessage = element.dataset.successMessage || '';
  const errorMessage = element.dataset.errorMessage || '';
  const showProcessList = element.dataset.showProcessList !== 'false';

  render(
    <BackgroundProcess
      type={type}
      successMessage={successMessage}
      errorMessage={errorMessage}
      showProcessList={showProcessList}
    />,
    element
  );
}
