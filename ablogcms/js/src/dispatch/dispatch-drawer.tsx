import { render } from '../utils/react';
import Portal from '../components/portal/portal';
import { DrawerController } from '../lib/drawer';

export default function dispatchDrawer() {
  const element = document.createDocumentFragment();

  DrawerController.openTrigger = '.js-acms-drawer-open';
  DrawerController.closeTrigger = '.js-acms-drawer-close';

  render(
    <Portal>
      <DrawerController />
    </Portal>,
    element
  );
}
