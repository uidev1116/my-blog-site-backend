import { memo } from 'react';
import { Editor } from '@tiptap/core';
import { MenuItem } from '@components/dropdown-menu';
import { Popover, PopoverTrigger, PopoverContent } from '@components/popover';
import { ColorPicker } from '@features/block-editor/components/panels';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { useTableStyle } from './hooks';

export const TableColorPicker = memo(({ editor }: { editor: Editor }) => {
  const { handleSetBgColor, handleClearBgColor } = useTableStyle(editor);

  return (
    <Popover modal placement="bottom-start">
      <PopoverTrigger asChild>
        <MenuItem icon={<Icon name="format_color_fill" />}>背景色</MenuItem>
      </PopoverTrigger>
      <PopoverContent data-elevation="3">
        <ColorPicker
          onChange={(color) => {
            if (/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color)) {
              handleSetBgColor(color);
            }
          }}
          onClear={() => {
            handleClearBgColor();
          }}
        />
      </PopoverContent>
    </Popover>
  );
});
