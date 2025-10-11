import { forwardRef, useCallback, cloneElement, isValidElement } from 'react';
import classnames from 'classnames';
import { Icon } from '@components/icon';
import { Slot, Slottable } from '../slot';
import { useMenuContext } from './context';
import { PolymorphicProps } from '../../types/polymorphic';
import useMergeRefs from '../../hooks/use-merge-refs';

interface SubmenuTriggerItemProps extends React.HTMLAttributes<HTMLDivElement>, PolymorphicProps {
  children?: React.ReactNode;

  /**
   * メニュー項目のバリアント
   * @default 'default'
   */
  variant?: 'default' | 'danger';

  /**
   * メニュー項目のアイコン
   */
  icon?: React.ReactNode;
}

/**
 * サブメニューのトリガーとなるメニュー項目コンポーネント
 * このコンポーネントは Menu コンポーネント内で使用され、クリックまたはホバーでサブメニューを表示します
 */
const SubmenuTriggerItem = forwardRef<HTMLDivElement, SubmenuTriggerItemProps>(
  ({ children, asChild, className, variant = 'default', icon, onFocus, ...props }, forwardRef) => {
    // メニューのコンテキスト
    const { isOpen, triggerRef, refs, listItem, getReferenceProps, hasFocusInside, setHasFocusInside, parentMenu } =
      useMenuContext();

    if (!parentMenu) {
      throw new Error('SubmenuTriggerItem must be used within a nested Menu component');
    }

    const { menuItemRefs } = parentMenu;

    const setRefs = useMergeRefs(
      triggerRef,
      forwardRef,
      refs.setReference,
      listItem.ref,
      (node: HTMLElement | null) => {
        if (node) {
          menuItemRefs.current = [...menuItemRefs.current, node];
        }
      }
    );

    const handleFocus = useCallback(
      (event: React.FocusEvent<HTMLDivElement>) => {
        onFocus?.(event);
        setHasFocusInside(false);
        parentMenu.setHasFocusInside(true);
      },
      [onFocus, setHasFocusInside, parentMenu]
    );

    const Component = asChild ? Slot : 'div';

    return (
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <Component
        {...props}
        ref={setRefs}
        role="menuitem"
        tabIndex={parentMenu.activeIndex === listItem.index ? 0 : -1}
        className={classnames(className, {
          'acms-admin-dropdown-menu-danger': variant === 'danger',
        })}
        data-open={isOpen ? '' : undefined}
        data-focus-inside={hasFocusInside ? '' : undefined}
        aria-label={ACMS.i18n('dropdown_menu.submenu_trigger_label')}
        {...getReferenceProps(
          parentMenu.getItemProps({
            ...props,
            onFocus: handleFocus,
          })
        )}
      >
        {isValidElement(icon) &&
          cloneElement(icon, {
            ...icon.props,
            'aria-hidden': true,
          })}
        <Slottable>{children}</Slottable>
        <span className="acms-admin-dropdown-menu-chevron">
          <Icon name="chevron_right" />
        </span>
      </Component>
    );
  }
);

SubmenuTriggerItem.displayName = 'SubmenuTriggerItem';

export default SubmenuTriggerItem;
