import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import { useCallback, useRef, useState } from 'react';
import { v4 as uuid } from 'uuid';

import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Popover, PopoverTrigger, PopoverContent } from '@components/popover';
import { MenuProps, ShouldShowProps } from '@features/block-editor/components/menus/types';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { ColorPicker } from '@features/block-editor/components/panels';
import { isColumnGripSelected } from '../TableColumn/utils';
import { isRowGripSelected } from '../TableRow/utils';
import { useTableStyle } from '../hooks';

export const TableMenu = ({ editor, appendTo }: MenuProps): JSX.Element => {
  const menuRef = useRef<HTMLDivElement>(null);
  const [isOpen, setIsOpen] = useState(false);
  const { handleSetBgColor, handleClearBgColor } = useTableStyle(editor);
  const { features } = useSettingsContext();
  const shouldShow = useCallback(
    ({ view, state, from }: ShouldShowProps) => {
      if (!state || !from) {
        return false;
      }
      return (
        editor.isActive('table') &&
        !isColumnGripSelected({ editor, view, from: from || 0 }) &&
        !isRowGripSelected({ editor, view, from })
      );
    },
    [editor]
  );

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey={`table-menu-${uuid()}`}
      shouldShow={shouldShow}
      updateDelay={0}
      tippyOptions={{
        offset: [0, 15],
        appendTo: () => appendTo?.current,
        popperOptions: {
          modifiers: [{ name: 'flip', enabled: true }],
        },
        getReferenceClientRect: () => {
          const { view } = editor;
          const { state } = editor;
          const { $from } = state.selection;
          // 親ノードを遡って table の dom を取得
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'table') {
              const pos = $from.before(depth);
              const dom = view.nodeDOM(pos);
              if (dom instanceof HTMLElement) {
                return dom.getBoundingClientRect();
              }
            }
          }
          return editor.view.dom.getBoundingClientRect(); // fallback
        },
      }}
    >
      <Toolbar ref={menuRef}>
        <Toolbar.Button
          type="button"
          tooltip="セルを結合"
          aria-label="セルを結合"
          onClick={() => editor.chain().focus().mergeCells().run()}
        >
          <Icon name="cell_merge" />
        </Toolbar.Button>
        <Toolbar.Button
          type="button"
          tooltip="セルを分離"
          aria-label="セルを分離"
          onClick={() => editor.chain().focus().splitCell().run()}
        >
          <Icon name="arrows_outward" />
        </Toolbar.Button>
        <Toolbar.Button
          type="button"
          tooltip="スクロールさせる"
          aria-label="テーブルをスクロールさせる"
          active={editor.getAttributes('table').scrollable}
          onClick={() => editor.chain().focus().toggleTableScrollable().run()}
        >
          <Icon name="swipe" />
        </Toolbar.Button>
        {features?.tableBgColor && (
          <Popover modal isOpen={isOpen} onOpenChange={setIsOpen} placement="top">
            <Toolbar.Divider />
            <PopoverTrigger asChild>
              <Toolbar.Button
                type="button"
                tooltip="セルの背景色"
                active={false}
                aria-label="セルの背景色を設定"
                onClick={() => setIsOpen((prev) => !prev)}
              >
                <Icon name="format_color_fill" />
              </Toolbar.Button>
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
                  setIsOpen(false);
                }}
              />
            </PopoverContent>
          </Popover>
        )}
      </Toolbar>
    </BaseBubbleMenu>
  );
};

export default TableMenu;
