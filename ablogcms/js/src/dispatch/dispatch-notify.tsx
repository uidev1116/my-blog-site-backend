import { render } from '../utils/react';
import Portal from '../components/portal/portal';
import { NotificationContainer, notify } from '../lib/notify';

export default function dispatchNotify() {
  const element = document.createDocumentFragment();

  render(
    <Portal>
      <NotificationContainer />
    </Portal>,
    element
  );

  ACMS.Library.notify = notify;
  ACMS.dispatchEvent('acmsNotifyReady');
}
