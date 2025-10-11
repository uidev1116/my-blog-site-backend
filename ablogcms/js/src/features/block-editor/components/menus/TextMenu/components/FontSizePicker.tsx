import { useCallback, useMemo } from 'react';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import {
  Menu,
  MenuTrigger,
  MenuList,
  MenuGroup,
  MenuItemRadio,
  MenuItemRadioGroup,
  MenuPopover,
} from '@components/dropdown-menu';

export type FontSizePickerProps = {
  onChange: (value: string) => void;
  value: string;
};

export const FontSizePicker = ({ onChange, value }: FontSizePickerProps) => {
  const { fontSize } = useSettingsContext();
  const currentValue = useMemo(() => fontSize?.find((size) => size.value === value), [fontSize, value]);
  const currentSizeLabel = useMemo(() => currentValue?.label.split(' ')[0] || '標準', [currentValue]);
  const selectSize = useCallback((size: string) => onChange(size), [onChange]);

  return (
    <Menu closeOnSelect>
      <MenuTrigger asChild>
        <Toolbar.Button
          type="button"
          tooltip="フォントサイズ"
          active={!!currentValue?.value}
          aria-label="フォントサイズを設定"
        >
          <Icon name="format_size" />
          {currentSizeLabel}
          <Icon name="keyboard_arrow_down" />
        </Toolbar.Button>
      </MenuTrigger>
      <MenuPopover data-elevation="3">
        <MenuList>
          {fontSize?.length > 0 && (
            <MenuGroup title="フォントサイズ">
              <MenuItemRadioGroup
                defaultValue={currentValue?.value}
                onValueChange={(event) => {
                  selectSize(event.detail.value);
                }}
              >
                {fontSize.map((item) => (
                  <MenuItemRadio key={`${item.label}_${item.value}`} value={item.value}>
                    <span style={{ fontSize: item.value }}>{item.label}</span>
                  </MenuItemRadio>
                ))}
              </MenuItemRadioGroup>
            </MenuGroup>
          )}
        </MenuList>
      </MenuPopover>
    </Menu>
  );
};
