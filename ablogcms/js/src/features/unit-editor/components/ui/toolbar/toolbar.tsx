import { forwardRef } from 'react';
import classnames from 'classnames';
import useToolbar from '../../../../../hooks/use-toolbar';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface ToolbarProps extends React.HTMLAttributes<HTMLDivElement> {}

const Toolbar = forwardRef<HTMLDivElement, ToolbarProps>(({ children, className, ...props }, ref) => {
  const { toolbarProps } = useToolbar();
  return (
    <div
      className={classnames('acms-admin-unit-toolbar', className)}
      {...toolbarProps({
        ...props,
        ref,
      })}
    >
      {children}
    </div>
  );
});

Toolbar.displayName = 'Toolbar';

export default Toolbar;
