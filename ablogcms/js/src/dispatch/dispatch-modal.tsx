import { render } from '../utils/react';
import Portal from '../components/portal/portal';
import { ModalController } from '../lib/modal';

export default function dispatchModal() {
  const element = document.createDocumentFragment();

  ModalController.openTrigger = '.js-acms-modal-open';
  ModalController.closeTrigger = '.js-acms-modal-close';

  render(
    <Portal>
      <ModalController />
    </Portal>,
    element
  );
}
