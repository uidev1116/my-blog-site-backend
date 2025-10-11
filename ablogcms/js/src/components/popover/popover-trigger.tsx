import { forwardRef } from 'react';
import { usePopoverContext } from './context';
import { Slot } from '../slot';
import useMergeRefs from '../../hooks/use-merge-refs';
import { PolymorphicProps } from '../../types/polymorphic';

// PopoverTriggerのプロパティ
export interface PopoverTriggerProps extends React.HTMLAttributes<HTMLButtonElement>, PolymorphicProps {
  children?: React.ReactNode;
}

/**
 * PopoverTrigger - ポップオーバーを開閉するためのトリガーコンポーネント
 */
const PopoverTrigger = forwardRef<HTMLButtonElement, PopoverTriggerProps>(
  ({ children, asChild = false, ...props }, forwardedRef) => {
    const context = usePopoverContext();
    const childrenRef = (children as any).ref; // eslint-disable-line @typescript-eslint/no-explicit-any
    const setRefs = useMergeRefs(context.refs.setReference, forwardedRef, childrenRef);

    const Comp = asChild ? Slot : 'button';
    return (
      <Comp
        ref={setRefs}
        type="button"
        // The user can style the trigger based on the state
        data-state={context.isOpen ? 'open' : 'closed'}
        {...context.getReferenceProps(props)}
      >
        {children}
      </Comp>
    );
  }
);

PopoverTrigger.displayName = 'PopoverTrigger';

export default PopoverTrigger;
