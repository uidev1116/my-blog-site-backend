import { forwardRef, useRef, useCallback, useState, isValidElement, cloneElement } from 'react';
import { useListItem, useFloatingTree } from '@floating-ui/react';
import { Icon } from '@components/icon';
import type { PolymorphicProps } from '../../types/polymorphic';
import { useMenuContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';
import { Slot, Slottable } from '../slot';
import { createMenuItemCheckedChangeEvent, type MenuItemCheckedChangeEvent } from './events';
import useUpdateEffect from '../../hooks/use-update-effect';

interface MenuItemCheckboxProps
  extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onClick' | 'onSelect'>,
    PolymorphicProps {
  children?: React.ReactNode;
  /**
   * メニュー項目の値
   */
  value?: string;
  /**
   * メニュー項目のチェック状態
   */
  checked?: boolean;
  /**
   * メニュー項目のチェック状態のデフォルト値
   */
  defaultChecked?: boolean;
  /**
   * メニュー項目のチェック状態の変更時のコールバック
   */
  onCheckedChange?: (event: MenuItemCheckedChangeEvent) => void;
  /**
   * メニュー項目のアイコン
   */
  icon?: React.ReactNode;
  /**
   * メニュー項目の無効状態
   */
  isDisabled?: boolean;
  /**
   * 選択時にメニューを閉じるかどうか
   */
  closeOnSelect?: boolean;
}

const MenuItemCheckbox = forwardRef<HTMLDivElement, MenuItemCheckboxProps>(
  (
    {
      children,
      asChild,
      value = '',
      checked: checkedProp,
      defaultChecked = false,
      onCheckedChange,
      icon,
      isDisabled,
      closeOnSelect,
      onKeyDown,
      onFocus,
      ...props
    },
    forwardRef
  ) => {
    const { menuItemRefs, activeIndex, getItemProps, setHasFocusInside } = useMenuContext();
    const ref = useRef<HTMLDivElement>(null);

    const item = useListItem({ label: isDisabled ? null : ref.current?.textContent });
    const tree = useFloatingTree();
    const isActive = item.index === activeIndex;

    const setRefs = useMergeRefs(item.ref, forwardRef, ref, (node: HTMLElement | null) => {
      if (node) {
        menuItemRefs.current = [...menuItemRefs.current, node];
      }
    });
    const [checked, setChecked] = useState(checkedProp || defaultChecked);

    const handleClick = useCallback(() => {
      if (isDisabled) {
        return;
      }
      setChecked((prev) => !prev);
    }, [setChecked, isDisabled]);

    const handleKeyDown = useCallback(
      (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (isDisabled) {
          return;
        }
        // 独自のキーダウンハンドラが指定されていれば、それを呼び出す
        if (onKeyDown) {
          onKeyDown(event);
        }
        if (event.key === ' ' || event.key === 'Enter') {
          setChecked((prev) => !prev);
        }
      },
      [setChecked, onKeyDown, isDisabled]
    );

    const handleFocus = useCallback(
      (event: React.FocusEvent<HTMLDivElement>) => {
        onFocus?.(event);
        setHasFocusInside(true);
      },
      [onFocus, setHasFocusInside]
    );
    useUpdateEffect(() => {
      if (onCheckedChange) {
        const event = createMenuItemCheckedChangeEvent({ checked, value });
        onCheckedChange?.(event);
      }
      tree?.events.emit('select', {
        closeOnSelect,
      });
      // 監視対象はcheckedのみにする
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [checked]);

    const Component = asChild ? Slot : 'div';

    return (
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <Component
        {...props}
        ref={setRefs}
        role="menuitemcheckbox"
        tabIndex={isActive ? 0 : -1}
        aria-checked={checked}
        aria-disabled={isDisabled}
        data-value={value}
        {...getItemProps({
          onClick: handleClick,
          onKeyDown: handleKeyDown,
          onFocus: handleFocus,
        })}
      >
        {isValidElement(icon) &&
          cloneElement(icon, {
            ...icon.props,
            'aria-hidden': true,
          })}
        <Slottable>{children}</Slottable>
        {checked && (
          <span className="acms-admin-dropdown-menu-checked">
            <Icon name="check" />
          </span>
        )}
      </Component>
    );
  }
);

MenuItemCheckbox.displayName = 'MenuItemCheckbox';

export default MenuItemCheckbox;
