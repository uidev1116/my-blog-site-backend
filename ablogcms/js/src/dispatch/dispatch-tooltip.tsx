import { Suspense, lazy } from 'react';
import { render } from '../utils/react';
import Portal from '../components/portal/portal';

export default function dispatchTooltip() {
  const element = document.createDocumentFragment();

  const TooltipManager = lazy(() => import(/* webpackChunkName: "tooltip-manager" */ '../lib/tooltip/tooltip-manager'));

  render(
    <Suspense fallback={null}>
      <Portal>
        <TooltipManager />
      </Portal>
    </Suspense>,
    element
  );
}
