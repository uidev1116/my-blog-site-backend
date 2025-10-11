import { memo, useCallback, useEffect, useState } from 'react';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import type { ImageSizesItem } from '@features/block-editor/types';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import {
  Menu,
  MenuTrigger,
  MenuList,
  MenuGroup,
  MenuItemRadio,
  MenuItemRadioGroup,
  MenuPopover,
} from '@components/dropdown-menu';

export type ImageBlockWidthProps = {
  onChange: (value: string) => void;
  value: string;
};

export const ImageBlockWidth = memo(({ onChange, value }: ImageBlockWidthProps) => {
  const { imageSizes } = useSettingsContext();
  const [currentValue, setCurrentValue] = useState<ImageSizesItem | null>(null);

  useEffect(() => {
    const sizeItem = imageSizes.find((item) => item.value === value) || imageSizes[0];
    setCurrentValue(sizeItem);
  }, [imageSizes, value]);

  const handleChange = useCallback(
    (value: string) => {
      onChange(value);
    },
    [onChange]
  );

  return (
    <Menu closeOnSelect>
      <MenuTrigger asChild>
        <Toolbar.Button type="button" tooltip="画像サイズ" aria-label="画像サイズを設定">
          <Icon name="crop_free" />
          {currentValue?.label || '1/1 (100%)'}
          <Icon name="keyboard_arrow_down" />
        </Toolbar.Button>
      </MenuTrigger>
      <MenuPopover size="medium" data-elevation="3">
        <MenuList>
          <MenuGroup title="画像サイズ">
            <MenuItemRadioGroup
              defaultValue={value}
              onValueChange={(event) => {
                handleChange(event.detail.value);
              }}
            >
              {imageSizes?.map((item) => (
                <MenuItemRadio key={`${item.label}_${item.value}`} value={item.value}>
                  {item.label}
                </MenuItemRadio>
              ))}
            </MenuItemRadioGroup>
          </MenuGroup>
        </MenuList>
      </MenuPopover>
    </Menu>
  );
});

ImageBlockWidth.displayName = 'ImageBlockWidth';
