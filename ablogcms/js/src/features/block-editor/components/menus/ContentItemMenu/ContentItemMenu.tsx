import { useEffect, useRef, useState, memo, useCallback } from 'react';
import DragHandle from '@tiptap/extension-drag-handle-react';
import { Editor } from '@tiptap/react';
import { NodeSelection, TextSelection } from '@tiptap/pm/state';
import type { BlockMenuItem } from '@features/block-editor/types';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Menu, MenuList, MenuItem, MenuDivider, MenuPopover } from '@components/dropdown-menu';
import useContentItemActions from './hooks/useContentItemActions';
import { IdEditor } from './components/IdEditor';
import { BlockMenus } from './components/BlockMenus';
import { useData } from './hooks/useData';

export type ContentItemMenuProps = {
  editor: Editor;
  getFilteredBlockMenus: ({ query, editor }: { query: string; editor: Editor }) => BlockMenuItem[];
};

export const ContentItemMenu = ({ editor, getFilteredBlockMenus }: ContentItemMenuProps) => {
  const menuAnchorRef = useRef<HTMLButtonElement>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const { currentNode, currentNodePos, handleNodeChange } = useData();
  const actions = useContentItemActions(editor, currentNode, currentNodePos);
  const MemoIdEditor = memo(IdEditor);
  const MemoBlockMenus = memo(BlockMenus);

  const getAnchorRect = () => menuAnchorRef.current!.getBoundingClientRect();

  // メニューが開いているときはドラッグハンドルをロックする
  useEffect(() => {
    if (menuOpen) {
      editor.commands.setMeta('lockDragHandle', true);
    } else {
      editor.commands.setMeta('lockDragHandle', false);
    }
  }, [editor, menuOpen]);

  // ショートカット
  useEffect(() => {
    const { dom } = editor.view;

    const handleKeyDown = (e: KeyboardEvent) => {
      const isMod = e.metaKey || e.ctrlKey;

      // 上に移動
      if (isMod && e.altKey && e.key === 'ArrowUp') {
        e.preventDefault();
        e.stopPropagation();
        actions.moveBlockUp();
      }

      // 下に移動
      if (isMod && e.altKey && e.key === 'ArrowDown') {
        e.preventDefault();
        e.stopPropagation();
        actions.moveBlockDown();
      }

      // 複製
      if (isMod && e.shiftKey && e.key.toLowerCase() === 'd') {
        e.preventDefault();
        e.stopPropagation();
        actions.duplicateNode();
      }

      // 書式をクリア
      if (isMod && e.shiftKey && e.key.toLowerCase() === 'x') {
        e.preventDefault();
        e.stopPropagation();
        actions.resetTextFormatting();
      }

      // 削除
      if (isMod && e.shiftKey && e.key.toLowerCase() === 'backspace') {
        e.preventDefault();
        e.stopPropagation();
        actions.deleteNode();
      }
    };

    dom.addEventListener('keydown', handleKeyDown);
    return () => dom.removeEventListener('keydown', handleKeyDown);
  }, [editor, actions]);

  const onChangeId = useCallback(
    (id: string) => {
      actions.setHeadingId(id);
    },
    [actions]
  );

  return (
    <DragHandle pluginKey="ContentItemMenu" editor={editor} onNodeChange={handleNodeChange}>
      <div className="acms-admin-block-editor-content-item-menu">
        <Toolbar.Button
          type="button"
          size="small"
          variant="secondary"
          onClick={actions.handleAdd}
          aria-label="新規ブロックを追加"
        >
          <Icon name="add" />
        </Toolbar.Button>
        <Menu isOpen={menuOpen} onOpenChange={setMenuOpen} getAnchorRect={getAnchorRect}>
          <Toolbar.Button
            type="button"
            size="small"
            variant="secondary"
            ref={menuAnchorRef}
            aria-label="ブロックメニューを開く"
            onClick={() => {
              const { state } = editor;
              const { tr } = state;
              const node = editor.state.doc.nodeAt(currentNodePos);

              if (!node) return;

              if (node.isTextblock) {
                // テキストブロックなら中にカーソルを入れる
                const textSelection = TextSelection.create(state.doc, currentNodePos + 1);
                editor.view.dispatch(tr.setSelection(textSelection).scrollIntoView());
              } else {
                // 非テキストブロック（imageBlockなど）はノード選択にする
                const nodeSelection = NodeSelection.create(state.doc, currentNodePos);
                editor.view.dispatch(tr.setSelection(nodeSelection).scrollIntoView());
              }

              setMenuOpen((prev) => !prev);
            }}
          >
            <Icon name="drag_indicator" />
          </Toolbar.Button>
          <MenuPopover size="large" data-elevation="3">
            <MenuList>
              <MemoBlockMenus
                menus={getFilteredBlockMenus({ query: '', editor })}
                editor={editor}
                currentPos={currentNodePos}
              />
              <MenuItem
                icon={<Icon name="keyboard_arrow_up" />}
                shortcut={['Mod', 'Alt', 'ArrowUp']}
                onSelect={() => actions.moveBlockUp(currentNode, currentNodePos)}
              >
                上に移動
              </MenuItem>
              <MenuItem
                icon={<Icon name="keyboard_arrow_down" />}
                shortcut={['Mod', 'Alt', 'ArrowDown']}
                onSelect={() => actions.moveBlockDown(currentNode, currentNodePos)}
              >
                下に移動
              </MenuItem>
              <MenuItem
                icon={<Icon name="library_add" />}
                shortcut={['Mod', 'Shift', 'D']}
                onSelect={() => actions.duplicateNode(currentNode, currentNodePos)}
              >
                複製
              </MenuItem>
              <MenuItem icon={<Icon name="content_copy" />} onSelect={actions.copyNodeToClipboard}>
                コピー
              </MenuItem>
              <MenuItem
                icon={<Icon name="format_clear" />}
                shortcut={['Mod', 'Shift', 'X']}
                onSelect={() => actions.resetTextFormatting(currentNode, currentNodePos)}
              >
                書式をクリア
              </MenuItem>
              <MenuItem
                variant="danger"
                icon={<Icon name="delete" />}
                shortcut={['Mod', 'Shift', 'Backspace']}
                onSelect={() => actions.deleteNode(currentNode, currentNodePos)}
              >
                削除
              </MenuItem>
              <MenuDivider />
              <MemoIdEditor onChange={onChangeId} value={currentNode?.attrs.id} />
            </MenuList>
          </MenuPopover>
        </Menu>
      </div>
    </DragHandle>
  );
};
