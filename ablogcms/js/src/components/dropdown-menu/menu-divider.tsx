import { forwardRef } from 'react';
import classnames from 'classnames';
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface MenuDividerProps extends React.HTMLAttributes<HTMLHRElement> {}

const MenuDivider = forwardRef<HTMLHRElement, MenuDividerProps>(({ className, ...props }, ref) => (
  <hr ref={ref} className={classnames('acms-admin-divider', className)} {...props} />
));

MenuDivider.displayName = 'MenuDivider';

export default MenuDivider;
