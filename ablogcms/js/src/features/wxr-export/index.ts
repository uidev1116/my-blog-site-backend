import backgroundProcess from '../../lib/background-process';

export default function dispatchCsvImport(context: Element | Document = document) {
  const element = context.querySelector('#js-background-wxr-export');
  if (!element) {
    return;
  }
  const type = element.getAttribute('data-type') || '';
  backgroundProcess('#js-background-wxr-export', type, 1000);
}
