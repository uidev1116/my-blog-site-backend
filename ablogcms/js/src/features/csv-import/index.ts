import backgroundProcess from '../../lib/background-process';

export default function dispatchCsvImport(context: Element | Document = document) {
  const element = context.querySelector('#js-background-csv-import');
  if (!element) {
    return;
  }
  const type = element.getAttribute('data-type') || '';
  backgroundProcess('#js-background-csv-import', type, 1000);
}
