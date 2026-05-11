import { render } from '../../utils/react';
import BackgroundProcess from '../background-process/background-process';

export default function dispatchWxrExport(context: Element | Document = document) {
  const element = context.querySelector<HTMLElement>('#js-background-wxr-export');
  if (!element) {
    return;
  }
  const type = element.dataset.type || '';
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
