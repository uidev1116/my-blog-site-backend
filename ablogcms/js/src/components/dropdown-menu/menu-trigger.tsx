import { forwardRef } from 'react';
import type { PolymorphicProps } from '../../types/polymorphic';
import { useMenuContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';
import { Slot } from '../slot';

interface MenuTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, PolymorphicProps {
  children?: React.ReactNode;
}

const MenuTrigger = forwardRef<HTMLButtonElement, MenuTriggerProps>(({ children, asChild, ...props }, ref) => {
  const { isOpen, triggerRef, menuPopoverRef, refs, listItem, getReferenceProps, hasFocusInside } = useMenuContext();

  const Component = asChild ? Slot : 'button';
  return (
    <Component
      ref={useMergeRefs(ref, triggerRef, refs.setReference, listItem.ref)}
      type="button"
      aria-controls={menuPopoverRef?.current?.id}
      data-open={isOpen ? '' : undefined}
      data-focus-inside={hasFocusInside ? '' : undefined}
      aria-label={ACMS.i18n('dropdown_menu.trigger_label')}
      {...getReferenceProps(props)}
    >
      {children}
    </Component>
  );
});

MenuTrigger.displayName = 'MenuTrigger';

export default MenuTrigger;
