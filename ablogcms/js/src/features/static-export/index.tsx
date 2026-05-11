import { render } from '../../utils/react';
import StaticExportProgress from './static-export-progress';

export default function dispatchStaticExport(context: Element | Document = document) {
  const element = context.querySelector<HTMLElement>('#js-static-export-progress');
  if (!element) {
    return;
  }
  const resultHeading = element.dataset.resultHeading || '';
  const removedFilesHeading = element.dataset.removedFilesHeading || '';
  const errorHeading = element.dataset.errorHeading || '';

  render(
    <StaticExportProgress
      resultHeading={resultHeading}
      removedFilesHeading={removedFilesHeading}
      errorHeading={errorHeading}
    />,
    element
  );
}
