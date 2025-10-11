import { forwardRef } from 'react';
import classnames from 'classnames';
import { FloatingFocusManager, FloatingPortal } from '@floating-ui/react';
import { usePopoverContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';

export interface PopoverContentProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
  /**
   * ポップオーバーのサイズ
   * @default 'default'
   */
  size?: 'small' | 'default';
}

/**
 * PopoverContent - ポップオーバーの内容を表示するコンポーネント
 */
const PopoverContent = forwardRef<HTMLDivElement, PopoverContentProps>(
  ({ children, className, style, size = 'default', ...props }, forwardedRef) => {
    const { context: floatingContext, focusManagerOptions, ...context } = usePopoverContext();
    const setRefs = useMergeRefs(context.refs.setFloating, forwardedRef);

    if (!floatingContext.open) {
      return null;
    }

    return (
      <FloatingPortal>
        <FloatingFocusManager context={floatingContext} modal={context.modal} {...focusManagerOptions}>
          <div
            ref={setRefs}
            className={classnames('acms-admin-popover', className, {
              small: size === 'small',
            })}
            style={{ ...context.floatingStyles, ...style }}
            {...context.getFloatingProps(props)}
          >
            {children}
          </div>
        </FloatingFocusManager>
      </FloatingPortal>
    );
  }
);

PopoverContent.displayName = 'PopoverContent';

export default PopoverContent;
