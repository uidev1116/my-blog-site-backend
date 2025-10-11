import classnames from 'classnames';
import { forwardRef } from 'react';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface ToolbarButtonGroupProps extends React.HTMLAttributes<HTMLDivElement> {}

const ToolbarButtonGroup = forwardRef<HTMLDivElement, ToolbarButtonGroupProps>(
  ({ children, className, ...props }, ref) => {
    return (
      <div ref={ref} role="group" className={classnames('acms-admin-unit-toolbar-button-group', className)} {...props}>
        {children}
      </div>
    );
  }
);

ToolbarButtonGroup.displayName = 'ToolbarButtonGroup';

export default ToolbarButtonGroup;
