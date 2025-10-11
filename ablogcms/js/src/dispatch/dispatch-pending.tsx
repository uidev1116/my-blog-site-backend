import { render } from '../utils/react';
import Portal from '../components/portal/portal';
import { PendingContainer, pending } from '../lib/pending';

export default function dispatchSplash() {
  const element = document.createDocumentFragment();
  render(
    <Portal>
      <PendingContainer />
    </Portal>,
    element
  );

  ACMS.Library.pending = pending;
  ACMS.dispatchEvent('acmsPendingReady');
}
