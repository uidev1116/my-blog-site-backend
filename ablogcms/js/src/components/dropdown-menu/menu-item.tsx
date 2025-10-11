import { forwardRef, useRef, useCallback, isValidElement, cloneElement } from 'react';
import classnames from 'classnames';
import { useFloatingTree, useListItem } from '@floating-ui/react';
import type { PolymorphicProps } from '../../types/polymorphic';
import { useMenuContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';
import { Slot, Slottable } from '../slot';
import { createMenuItemSelectEvent, type MenuItemSelectEvent } from './events';
import { commandList } from '../../config/command';

interface MenuItemCommandProps extends React.HTMLAttributes<HTMLSpanElement> {
  commands: string[];
}

const MenuItemCommand = forwardRef<HTMLSpanElement, MenuItemCommandProps>(
  ({ commands, className, ...props }, ref): JSX.Element => {
    return (
      <span className={classnames('acms-admin-dropdown-menu-item-command', className)} ref={ref} {...props}>
        {commands.map((command) => (
          <kbd key={command}>{commandList.find((c) => c.id === command)?.command || command}</kbd>
        ))}
      </span>
    );
  }
);

MenuItemCommand.displayName = 'MenuItemCommand';

interface MenuItemProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onSelect' | 'onClick'>, PolymorphicProps {
  children?: React.ReactNode;
  /**
   * メニュー項目の値
   */
  value?: string;

  /**
   * メニュー項目の選択時のコールバック
   */
  onSelect?: (event: MenuItemSelectEvent) => void;
  /**
   * メニュー項目のバリアント
   * @default 'default'
   */
  variant?: 'default' | 'danger';

  /**
   * メニュー項目のアイコン
   */
  icon?: React.ReactNode;

  shortcut?: string[];

  /**
   * disabled
   */
  isDisabled?: boolean;

  /**
   * メニュー項目のアクティブ状態
   */
  isActive?: boolean;

  /**
   * 選択時にメニューを閉じるかどうか
   */
  closeOnSelect?: boolean;
}

const MenuItem = forwardRef<HTMLDivElement, MenuItemProps>(
  (
    {
      children,
      onSelect,
      value = '',
      asChild,
      variant = 'default',
      className,
      icon,
      shortcut,
      onKeyDown,
      onFocus,
      isDisabled,
      isActive: isActiveProp,
      closeOnSelect,
      ...props
    },
    forwardRef
  ) => {
    const { menuItemRefs, activeIndex, getItemProps, setHasFocusInside } = useMenuContext();
    const ref = useRef<HTMLDivElement>(null);

    const item = useListItem({ label: isDisabled ? null : ref.current?.textContent });
    const tree = useFloatingTree();
    const isActive = item.index === activeIndex || isActiveProp;

    const setRefs = useMergeRefs(item.ref, forwardRef, ref, (node: HTMLElement | null) => {
      if (node) {
        menuItemRefs.current = [...menuItemRefs.current, node];
      }
    });
    const selectMenuItem = useCallback(
      (value: string) => {
        if (onSelect) {
          const selectEvent = createMenuItemSelectEvent({ value });
          onSelect(selectEvent);
        }
        // setTimeout で遅延させないとaタグによるページ遷移が発生しない不具合が発生する。
        setTimeout(() => {
          tree?.events.emit('select', {
            closeOnSelect,
          });
        }, 0);
      },
      [onSelect, tree?.events, closeOnSelect]
    );

    const handleClick = useCallback(() => {
      if (isDisabled) {
        return;
      }
      selectMenuItem(value);
    }, [selectMenuItem, value, isDisabled]);

    const handleFocus = useCallback(
      (event: React.FocusEvent<HTMLDivElement>) => {
        onFocus?.(event);
        setHasFocusInside(true);
      },
      [onFocus, setHasFocusInside]
    );

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
          selectMenuItem(value);
        }
      },
      [selectMenuItem, value, onKeyDown, isDisabled]
    );

    const Component = asChild ? Slot : 'div';

    return (
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <Component
        {...props}
        ref={setRefs}
        role="menuitem"
        tabIndex={isActive ? 0 : -1}
        className={classnames(className, {
          'acms-admin-dropdown-menu-danger': variant === 'danger',
          'acms-admin-dropdown-menu-active': isActive,
        })}
        aria-disabled={isDisabled}
        data-value={value}
        {...getItemProps({
          onClick: handleClick,
          onFocus: handleFocus,
          onKeyDown: handleKeyDown,
        })}
      >
        {isValidElement(icon) &&
          cloneElement(icon, {
            ...icon.props,
            'aria-hidden': true,
          })}
        <Slottable>{children}</Slottable>
        {shortcut && <MenuItemCommand commands={shortcut ?? []} />}
      </Component>
    );
  }
);

MenuItem.displayName = 'MenuItem';

export default MenuItem;
