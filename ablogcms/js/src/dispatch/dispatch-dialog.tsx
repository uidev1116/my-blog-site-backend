import { render } from '../utils/react';
import Portal from '../components/portal/portal';
import { DialogContainer, dialog } from '../lib/dialog';

export default function dispatchDialog() {
  const element = document.createDocumentFragment();

  render(
    <Portal>
      <DialogContainer />
    </Portal>,
    element
  );

  ACMS.Library.dialog = dialog;
  ACMS.dispatchEvent('acmsDialogReady');
}
