import { forwardRef, useImperativeHandle } from 'react';
import { PopoverContext } from './context';
import usePopover, { type PopoverOptions } from './use-popover';
import type { PopoverRef } from './types';

interface PopoverProps extends PopoverOptions {
  children: React.ReactNode;
}

/**
 * Popoverコンポーネント - ポップオーバーのコンテナであり、状態を管理します
 */
const Popover = forwardRef<PopoverRef, PopoverProps>(({ children, modal = false, ...restOptions }, ref) => {
  // This can accept any props as options, e.g. `placement`,
  // or other positioning options.
  const popover = usePopover({ modal, ...restOptions });
  useImperativeHandle(ref, () => ({
    openPopover: popover.openPopover,
    closePopover: popover.closePopover,
    togglePopover: popover.togglePopover,
  }));
  return <PopoverContext.Provider value={popover}>{children}</PopoverContext.Provider>;
});

Popover.displayName = 'Popover';

export default Popover;
