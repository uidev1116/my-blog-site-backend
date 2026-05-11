import { render } from '../../utils/react';
import BackgroundProcess from '../background-process/background-process';

function mount(element: HTMLElement, type: string) {
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

export default function dispatchBackup(context: Element | Document = document) {
  const databaseExport = context.querySelector<HTMLElement>('#js-database-export');
  if (databaseExport) {
    mount(databaseExport, 'backup_db');
  }
  const archivesExport = context.querySelector<HTMLElement>('#js-archives-export');
  if (archivesExport) {
    mount(archivesExport, 'backup_archives');
  }
}
