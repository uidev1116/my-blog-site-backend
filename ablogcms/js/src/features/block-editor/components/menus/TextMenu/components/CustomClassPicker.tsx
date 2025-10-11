import { useCallback, useEffect, useState } from 'react';
import type { CustomClassItem } from '@features/block-editor/types';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import {
  Menu,
  MenuItem,
  MenuTrigger,
  MenuList,
  MenuGroup,
  MenuItemRadio,
  MenuItemRadioGroup,
  MenuPopover,
} from '@components/dropdown-menu';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';

export type CustomClassPickerProps = {
  onChange: (value: string) => void;
  onRemove: () => void;
  value: string;
};

export const CustomClassPicker = ({ onChange, onRemove, value }: CustomClassPickerProps) => {
  const { customClass } = useSettingsContext();
  const [currentValue, setCurrentValue] = useState<CustomClassItem | null>(null);

  useEffect(() => {
    const currentValue = customClass.find((item) => item.value === value.replace('custom-mark ', ''));
    setCurrentValue(currentValue || null);
  }, [customClass, value]);

  const selectClass = useCallback(
    (className: string) => {
      onChange(`custom-mark ${className}`);
    },
    [onChange]
  );

  return (
    <Menu closeOnSelect>
      <MenuTrigger asChild>
        <Toolbar.Button
          type="button"
          tooltip="カスタムクラス"
          active={!!currentValue?.value}
          aria-label="カスタムクラスを設定"
        >
          <Icon name="build" />
          {currentValue?.label || 'カスタムクラス'}
          <Icon name="keyboard_arrow_down" />
        </Toolbar.Button>
      </MenuTrigger>
      <MenuPopover data-elevation="3">
        <MenuList>
          <MenuGroup title="カスタムクラス">
            <MenuItemRadioGroup
              defaultValue={currentValue?.value}
              onValueChange={(event) => {
                selectClass(event.detail.value);
              }}
            >
              {customClass?.map((item) => (
                <MenuItemRadio key={`${item.label}_${item.value}`} value={item.value} icon={<Icon name="build" />}>
                  {item.label}
                </MenuItemRadio>
              ))}
              <MenuItem variant="danger" icon={<Icon name="delete" />} onSelect={onRemove}>
                カスタムクラスを解除
              </MenuItem>
            </MenuItemRadioGroup>
          </MenuGroup>
        </MenuList>
      </MenuPopover>
    </Menu>
  );
};
