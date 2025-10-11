import { forwardRef } from 'react';

interface SpinnerProps {
  size?: React.CSSProperties['width'];
  verticalAlign?: React.CSSProperties['verticalAlign'];
  borderWidth?: React.CSSProperties['borderWidth'];
  speed?: React.CSSProperties['animationDuration'];
}

const Spinner = forwardRef<HTMLDivElement, SpinnerProps>(({ size, verticalAlign, borderWidth, speed }, ref) => (
  <div
    className="acms-admin-spinner"
    style={
      {
        '--acms-admin-spinner-size': typeof size === 'number' ? `${size}px` : size,
        '--acms-admin-spinner-vertical-align': verticalAlign,
        '--acms-admin-spinner-border-width': borderWidth,
        '--acms-admin-spinner-animation-speed': speed,
      } as React.CSSProperties
    }
    role="status"
    ref={ref}
  />
));

Spinner.displayName = 'Spinner';

export default Spinner;
