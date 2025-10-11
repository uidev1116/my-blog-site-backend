import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import React, { useCallback, useState } from 'react';
import { Menu, MenuList, MenuItem, MenuPopover, MenuDivider } from '@components/dropdown-menu';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { MenuProps, ShouldShowProps } from '@features/block-editor/components/menus/types';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { isColumnGripSelected } from './utils';
import { TableColorPicker } from '../ColorPicker';

export const TableColumnMenu = React.memo(({ editor, appendTo }: MenuProps): JSX.Element => {
  const { features } = useSettingsContext();
  const [isOpen, setIsOpen] = useState<boolean>(false);
  const [menuRect, setMenuRect] = useState<DOMRect | null>(null);
  const shouldShow = useCallback(
    ({ view, state, from }: ShouldShowProps) => {
      if (!state) {
        setIsOpen(false);
        return false;
      }
      const show = isColumnGripSelected({ editor, view, from: from || 0 });
      setIsOpen(show);
      return show;
    },
    [editor]
  );

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey="tableColumnMenu"
      updateDelay={0}
      tippyOptions={{
        appendTo: () => appendTo?.current,
        placement: 'bottom-end',
        offset: [0, 15],
        popperOptions: {
          modifiers: [{ name: 'flip', enabled: false }],
        },
        getReferenceClientRect: () => {
          const { view } = editor;
          const { state } = editor;
          const { $from } = state.selection;
          const pos = $from.before($from.depth);
          const dom = view.nodeDOM(pos);
          if (dom instanceof HTMLElement) {
            const rect = dom.getBoundingClientRect();
            setMenuRect(rect);
            return rect;
          }
          // fallback
          setMenuRect(editor.view.dom.getBoundingClientRect());
          return editor.view.dom.getBoundingClientRect();
        },
      }}
      shouldShow={shouldShow}
    >
      <Menu isOpen={isOpen} getAnchorRect={() => menuRect}>
        <MenuPopover size="small" data-elevation="3">
          <MenuList>
            <MenuItem
              icon={<Icon name="splitscreen_left" />}
              onSelect={() => editor.chain().focus().toggleHeaderCell().run()}
            >
              列見出し
            </MenuItem>
            <MenuDivider />
            <MenuItem icon={<Icon name="arrow_back" />} onSelect={() => editor.chain().focus().addColumnBefore().run()}>
              左に挿入
            </MenuItem>
            <MenuItem
              icon={<Icon name="arrow_forward" />}
              onSelect={() => editor.chain().focus().addColumnAfter().run()}
            >
              右に挿入
            </MenuItem>
            <MenuItem icon={<Icon name="cell_merge" />} onSelect={() => editor.chain().focus().mergeCells().run()}>
              セルを結合
            </MenuItem>
            <MenuItem icon={<Icon name="arrows_outward" />} onSelect={() => editor.chain().focus().splitCell().run()}>
              セルを分離
            </MenuItem>
            {features?.tableBgColor && <TableColorPicker editor={editor} />}
            <MenuItem
              variant="danger"
              icon={<Icon name="delete" />}
              onSelect={() => editor.chain().focus().deleteColumn().run()}
            >
              削除
            </MenuItem>
          </MenuList>
        </MenuPopover>
      </Menu>
    </BaseBubbleMenu>
  );
});

TableColumnMenu.displayName = 'TableColumnMenu';

export default TableColumnMenu;
