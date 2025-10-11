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

export type FontFamilyPickerProps = {
  onChange: (value: string) => void;
  value: string;
};

export const FontFamilyPicker = ({ onChange, value }: FontFamilyPickerProps) => {
  const { fontFamily } = useSettingsContext();
  const currentValue = useMemo(() => fontFamily?.find((family) => family.value === value), [fontFamily, value]);
  const currentFontLabel = useMemo(() => currentValue?.label || 'デフォルト', [currentValue]);
  const selectFont = useCallback((font: string) => onChange(font), [onChange]);

  return (
    <Menu closeOnSelect>
      <MenuTrigger asChild>
        <Toolbar.Button
          type="button"
          tooltip="フォントファミリー"
          active={!!currentValue?.value}
          aria-label="フォントファミリーを設定"
        >
          <Icon name="slab_serif" />
          {currentFontLabel}
          <Icon name="keyboard_arrow_down" />
        </Toolbar.Button>
      </MenuTrigger>
      <MenuPopover data-elevation="3">
        <MenuList>
          {fontFamily?.length > 0 && (
            <MenuGroup title="フォントファミリー">
              <MenuItemRadioGroup
                defaultValue={currentValue?.value}
                onValueChange={(event) => {
                  selectFont(event.detail.value);
                }}
              >
                {fontFamily.map((item) => (
                  <MenuItemRadio key={`${item.label}_${item.value}`} value={item.value}>
                    <span style={{ fontFamily: item.value }}>{item.label}</span>
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
