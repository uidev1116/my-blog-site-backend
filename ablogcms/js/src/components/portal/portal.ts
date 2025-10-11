import { useMemo } from 'react';
import { createPortal } from 'react-dom';

interface PortalProps {
  children: React.ReactNode;
  container?: string | HTMLElement | null;
}

const Portal = (props: PortalProps) => {
  const { children, container } = props;
  const portalContainer = useMemo(() => {
    if (typeof container === 'string') {
      return document.getElementById(container);
    }

    return container || document.body;
  }, [container]);

  return portalContainer ? createPortal(children, portalContainer) : null;
};

export default Portal;
