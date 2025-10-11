import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import React, { useCallback, useState } from 'react';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Menu, MenuList, MenuItem, MenuPopover, MenuDivider } from '@components/dropdown-menu';
import { MenuProps, ShouldShowProps } from '@features/block-editor/components/menus/types';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { isRowGripSelected } from './utils';
import { TableColorPicker } from '../ColorPicker';

export const TableRowMenu = React.memo(({ editor, appendTo }: MenuProps): JSX.Element => {
  const { features } = useSettingsContext();
  const [isOpen, setIsOpen] = useState<boolean>(false);
  const [menuRect, setMenuRect] = useState<DOMRect | null>(null);
  const shouldShow = useCallback(
    ({ view, state, from }: ShouldShowProps) => {
      if (!state || !from) {
        setIsOpen(false);
        return false;
      }
      const show = isRowGripSelected({ editor, view, from });
      setIsOpen(show);
      return show;
    },
    [editor]
  );

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey="tableRowMenu"
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
              icon={<Icon name="splitscreen_top" />}
              onSelect={() => editor.chain().focus().toggleHeaderCell().run()}
            >
              行見出し
            </MenuItem>
            <MenuDivider />
            <MenuItem icon={<Icon name="arrow_upward" />} onSelect={() => editor.chain().focus().addRowBefore().run()}>
              上に挿入
            </MenuItem>
            <MenuItem icon={<Icon name="arrow_downward" />} onSelect={() => editor.chain().focus().addRowAfter().run()}>
              下に挿入
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
              onSelect={() => editor.chain().focus().deleteRow().run()}
            >
              削除
            </MenuItem>
          </MenuList>
        </MenuPopover>
      </Menu>
    </BaseBubbleMenu>
  );
});

TableRowMenu.displayName = 'TableRowMenu';

export default TableRowMenu;
