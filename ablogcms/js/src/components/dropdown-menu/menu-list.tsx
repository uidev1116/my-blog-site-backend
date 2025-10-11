import { forwardRef, useId } from 'react';
import classnames from 'classnames';
import { useMenuContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';

interface MenuListProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const MenuList = forwardRef<HTMLDivElement, MenuListProps>(({ children, className, ...props }, ref) => {
  const id = useId();

  const { menuListRef } = useMenuContext();
  const setRefs = useMergeRefs(ref, menuListRef);

  return (
    // eslint-disable-next-line jsx-a11y/interactive-supports-focus
    <div ref={setRefs} id={id} role="menu" className={classnames('acms-admin-dropdown-menu', className)} {...props}>
      {children}
    </div>
  );
});

MenuList.displayName = 'MenuList';

export default MenuList;
