import { useState, useMemo } from 'react';
import { useFloatingTree } from '@floating-ui/react';
import { createMenuItemValueChangeEvent, type MenuItemValueChangeEvent } from './events';
import useUpdateEffect from '../../hooks/use-update-effect';
import { MenuRadioGroupContext } from './context';

interface MenuItemRadioGroupProps {
  children?: React.ReactNode;
  value?: string;
  defaultValue?: string;
  onValueChange?: (event: MenuItemValueChangeEvent) => void;
  closeOnSelect?: boolean;
}

const MenuItemRadioGroup = ({
  children,
  value: valueProp,
  defaultValue = '',
  onValueChange,
  closeOnSelect,
}: MenuItemRadioGroupProps) => {
  const [value, setValue] = useState(valueProp || defaultValue);

  const tree = useFloatingTree();

  useUpdateEffect(() => {
    if (onValueChange) {
      const event = createMenuItemValueChangeEvent({ value });
      onValueChange(event);
    }
    tree?.events.emit('select', {
      closeOnSelect,
    });
    // 監視対象はvalueのみにする
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  const contextValue = useMemo(() => ({ value, setValue, onValueChange }), [value, setValue, onValueChange]);

  return <MenuRadioGroupContext.Provider value={contextValue}>{children}</MenuRadioGroupContext.Provider>;
};

export default MenuItemRadioGroup;
