import { forwardRef, useRef, useCallback, useMemo, isValidElement, cloneElement } from 'react';
import { useListItem } from '@floating-ui/react';
import { Icon } from '@components/icon';
import type { PolymorphicProps } from '../../types/polymorphic';
import { useMenuContext, useMenuRadioGroupContext } from './context';
import useMergeRefs from '../../hooks/use-merge-refs';
import { Slot, Slottable } from '../slot';

interface MenuItemRadioProps
  extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onClick' | 'onSelect'>,
    PolymorphicProps {
  children?: React.ReactNode;
  /**
   * メニュー項目の値
   */
  value?: string;

  /**
   * メニュー項目のアイコン
   */
  icon?: React.ReactNode;

  /**
   * disabled
   */
  isDisabled?: boolean;
}

const MenuItemRadio = forwardRef<HTMLDivElement, MenuItemRadioProps>(
  ({ children, asChild, value: valueProp = '', icon, isDisabled, onKeyDown, onFocus, ...props }, forwardRef) => {
    const { menuItemRefs, activeIndex, getItemProps, setHasFocusInside } = useMenuContext();
    const ref = useRef<HTMLDivElement>(null);

    const item = useListItem({ label: isDisabled ? null : ref.current?.textContent });
    const isActive = item.index === activeIndex;

    const setRefs = useMergeRefs(item.ref, forwardRef, ref, (node: HTMLElement | null) => {
      if (node) {
        menuItemRefs.current = [...menuItemRefs.current, node];
      }
    });

    const { value, setValue } = useMenuRadioGroupContext();

    const handleClick = useCallback(() => {
      if (isDisabled) {
        return;
      }
      setValue(valueProp);
    }, [setValue, valueProp, isDisabled]);

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
          setValue(valueProp);
        }
      },
      [setValue, valueProp, onKeyDown, isDisabled]
    );

    const checked = useMemo(() => {
      if (value === '') {
        return false;
      }
      if (valueProp === '') {
        return false;
      }
      return value === valueProp;
    }, [value, valueProp]);

    const Component = asChild ? Slot : 'div';

    return (
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <Component
        {...props}
        ref={setRefs}
        role="menuitemradio"
        tabIndex={isActive ? 0 : -1}
        aria-checked={checked}
        aria-disabled={isDisabled}
        data-value={valueProp}
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
        {checked && (
          <span className="acms-admin-dropdown-menu-checked">
            <Icon name="check" />
          </span>
        )}
      </Component>
    );
  }
);

MenuItemRadio.displayName = 'MenuItemRadio';

export default MenuItemRadio;
