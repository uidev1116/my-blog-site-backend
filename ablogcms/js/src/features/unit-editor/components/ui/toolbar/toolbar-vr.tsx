import classnames from 'classnames';
import { forwardRef } from 'react';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface ToolbarVrProps extends React.HTMLAttributes<HTMLDivElement> {}

const ToolbarVr = forwardRef<HTMLDivElement, ToolbarVrProps>(({ className, ...props }, ref) => {
  return <div className={classnames('acms-admin-unit-toolbar-vr', className)} {...props} ref={ref} />;
});

ToolbarVr.displayName = 'ToolbarVr';

export default ToolbarVr;
