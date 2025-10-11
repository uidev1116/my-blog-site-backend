import { forwardRef } from 'react';
import classnames from 'classnames';
import { FloatingFocusManager, FloatingList, FloatingPortal } from '@floating-ui/react';
import { useMenuContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';

interface MenuPopoverProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
  size?: 'small' | 'medium' | 'large';
  scrollable?: boolean;
}

const MenuPopover = forwardRef<HTMLDivElement, MenuPopoverProps>(
  ({ children, className, style, scrollable = false, size, ...props }, ref) => {
    const {
      isOpen,
      menuPopoverRef,
      elementsRef,
      labelsRef,
      context,
      isNested,
      refs,
      floatingStyles,
      getFloatingProps,
      elevation,
      focusManagerOptions,
    } = useMenuContext();
    const setRefs = useMergeRefs(ref, menuPopoverRef, refs.setFloating);

    return (
      <FloatingList elementsRef={elementsRef} labelsRef={labelsRef}>
        {isOpen && (
          <FloatingPortal>
            <FloatingFocusManager
              context={context}
              modal={false}
              initialFocus={isNested ? -1 : 0}
              returnFocus={!isNested}
              {...focusManagerOptions}
            >
              <div
                ref={setRefs}
                tabIndex={-1}
                data-elevation={elevation}
                className={classnames('acms-admin-dropdown-menu-popover', className, {
                  'acms-admin-dropdown-menu-scrollable': scrollable,
                  'acms-admin-dropdown-menu-sm': size === 'small',
                  'acms-admin-dropdown-menu-lg': size === 'large',
                })}
                style={{ ...floatingStyles, ...style }}
                {...getFloatingProps(props)}
              >
                {children}
              </div>
            </FloatingFocusManager>
          </FloatingPortal>
        )}
      </FloatingList>
    );
  }
);

MenuPopover.displayName = 'MenuPopover';

export default MenuPopover;
