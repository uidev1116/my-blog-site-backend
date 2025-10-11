import { forwardRef, useId } from 'react';
import classnames from 'classnames';

interface MenuGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const MenuGroup = forwardRef<HTMLDivElement, MenuGroupProps>(({ children, className, title, ...props }, ref) => {
  const labelId = useId();
  return (
    <div
      ref={ref}
      role="group"
      className={classnames('acms-admin-dropdown-menu-group', className)}
      aria-labelledby={title ? labelId : undefined}
      {...props}
    >
      {title && (
        <span id={labelId} className="acms-admin-dropdown-menu-group-title">
          {title}
        </span>
      )}
      {children}
    </div>
  );
});

MenuGroup.displayName = 'MenuGroup';

export default MenuGroup;
