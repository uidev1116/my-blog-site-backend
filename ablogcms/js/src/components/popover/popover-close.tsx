import { forwardRef } from 'react';
import { usePopoverContext } from './context';
import { Slot } from '../slot';
import { PolymorphicProps } from '../../types/polymorphic';

export interface PopoverCloseProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, PolymorphicProps {
  children?: React.ReactNode;
}

/**
 * PopoverClose - ポップオーバーを閉じるためのコンポーネント
 */
const PopoverClose = forwardRef<HTMLButtonElement, PopoverCloseProps>(({ children, asChild, ...props }, ref) => {
  const { closePopover } = usePopoverContext();

  const Comp = asChild ? Slot : 'button';
  return (
    <Comp
      type="button"
      ref={ref}
      {...props}
      onClick={(event) => {
        props.onClick?.(event);
        closePopover();
      }}
    >
      {children}
    </Comp>
  );
});

PopoverClose.displayName = 'PopoverClose';

export default PopoverClose;
