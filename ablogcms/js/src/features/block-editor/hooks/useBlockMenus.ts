import { Editor } from '@tiptap/core';
import { NodeSelection } from '@tiptap/pm/state';
import { useCallback, useEffect, useRef, useMemo } from 'react';
import type { BlockMenuItem, CommandItem } from '@features/block-editor/types';
import { useFrequentlyUsed } from '@features/block-editor/hooks/useFrequentlyUsed';
import { ColumnLayout } from '@features/block-editor/extensions/MultiColumn/Columns';

interface actionCommand {
  action: (editor: Editor) => void;
  convert: (editor: Editor, pos: number) => void;
  isDisabled: (editor: Editor, pos: number) => boolean;
  isActive: (editor: Editor) => boolean;
}

const resolveCommands = (commands: CommandItem[], recordUsage: (command: string) => void): CommandItem[] => {
  const actions: Record<string, (cmd: CommandItem) => actionCommand> = {
    paragraph: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setParagraph().run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setParagraph().run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('paragraph', { class: cmd.class })
            : editor.isActive('paragraph', { class: null }) || editor.isActive('paragraph', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('paragraph')) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setParagraph();
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('paragraph', { class: cmd.class });
          }
          return editor.isActive('paragraph', { class: null }) || editor.isActive('paragraph', { class: '' });
        },
      };
    },
    heading1: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 1 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 1 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 1, class: cmd.class })
            : editor.isActive('heading', { level: 1, class: null }) ||
              editor.isActive('heading', { level: 1, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 1 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 1 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 1, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 1, class: null }) || editor.isActive('heading', { level: 1, class: '' })
          );
        },
      };
    },
    heading2: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 2 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 2 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 2, class: cmd.class })
            : editor.isActive('heading', { level: 2, class: null }) ||
              editor.isActive('heading', { level: 2, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 2 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 2 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 2, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 2, class: null }) || editor.isActive('heading', { level: 2, class: '' })
          );
        },
      };
    },
    heading3: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 3 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 3 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 3, class: cmd.class })
            : editor.isActive('heading', { level: 3, class: null }) ||
              editor.isActive('heading', { level: 3, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 3 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 3 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 3, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 3, class: null }) || editor.isActive('heading', { level: 3, class: '' })
          );
        },
      };
    },
    heading4: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 4 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 4 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 4, class: cmd.class })
            : editor.isActive('heading', { level: 4, class: null }) ||
              editor.isActive('heading', { level: 4, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 4 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 4 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 4, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 4, class: null }) || editor.isActive('heading', { level: 4, class: '' })
          );
        },
      };
    },
    heading5: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 5 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 5 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 5, class: cmd.class })
            : editor.isActive('heading', { level: 5, class: null }) ||
              editor.isActive('heading', { level: 5, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 5 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 5 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 5, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 5, class: null }) || editor.isActive('heading', { level: 5, class: '' })
          );
        },
      };
    },
    heading6: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setHeading({ level: 6 }).run();
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setHeading({ level: 6 }).run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('heading', { level: 6, class: cmd.class })
            : editor.isActive('heading', { level: 6, class: null }) ||
              editor.isActive('heading', { level: 6, class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('heading', { level: 6 })) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setHeading({ level: 6 });
        },
        isActive: (editor) => {
          if (cmd.class) {
            return editor.isActive('heading', { level: 6, class: cmd.class });
          }
          return (
            editor.isActive('heading', { level: 6, class: null }) || editor.isActive('heading', { level: 6, class: '' })
          );
        },
      };
    },
    bulletList: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().toggleBulletList().run();
          const { $from } = editor.state.selection;
          // カーソル位置から上へ順に探索して `bulletList` を見つける
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'bulletList') {
              const pos = $from.before(depth);
              editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
              break;
            }
          }
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).splitBulletList(pos).run();

          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('bulletList', { class: cmd.class })
            : editor.isActive('bulletList', { class: null }) || editor.isActive('bulletList', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('bulletList') || editor.isActive('orderedList')) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().toggleBulletList();
        },
        isActive: (editor) => {
          return editor.isActive('bulletList');
        },
      };
    },
    numberedList: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().toggleOrderedList().run();
          const { $from } = editor.state.selection;
          // カーソル位置から上へ順に探索して `orderedList` を見つける
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'orderedList') {
              const pos = $from.before(depth);
              editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
              break;
            }
          }
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).splitOrderedList(pos).run();

          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('orderedList', { class: cmd.class })
            : editor.isActive('orderedList', { class: null }) || editor.isActive('orderedList', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('orderedList') || editor.isActive('bulletList')) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().toggleOrderedList();
        },
        isActive: (editor) => {
          return editor.isActive('orderedList');
        },
      };
    },
    blockquote: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setBlockquote().run();
          const { $from } = editor.state.selection;
          // カーソル位置から上へ順に探索して `blockquote` を見つける
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'blockquote') {
              const pos = $from.before(depth);
              editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
              break;
            }
          }
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.setBlockquote().run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('blockquote', { class: cmd.class })
            : editor.isActive('blockquote', { class: null }) || editor.isActive('blockquote', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('blockquote')) {
            return false;
          }
          // それ以外は通常の can 判定
          return true;
        },
        isActive: (editor) => {
          return editor.isActive('blockquote');
        },
      };
    },
    codeBlock: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().setCodeBlock().run();
          const { $from } = editor.state.selection;
          // カーソル位置から上へ順に探索して `codeBlock` を見つける
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'codeBlock') {
              const pos = $from.before(depth);
              editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
              break;
            }
          }
        },
        convert: (editor, pos) => {
          const chain = editor.chain();
          chain.setNodeSelection(pos);
          chain.removeList(pos).removeBlockquote(pos).setCodeBlock().run();
          const { $from } = editor.state.selection;
          const newPos = $from.before(1);
          editor.chain().focus().setBlockAttrs(newPos, { class: cmd.class }).run();
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('codeBlock', { class: cmd.class })
            : editor.isActive('codeBlock', { class: null }) || editor.isActive('codeBlock', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('codeBlock')) {
            return false;
          }
          // それ以外は通常の can 判定
          return !editor.can().setCodeBlock();
        },
        isActive: (editor) => {
          return editor.isActive('codeBlock');
        },
      };
    },
    image: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().uploadMediaImage().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor) => {
          const { state } = editor;
          const { selection } = state;
          if (
            editor.isActive('imageBlock') ||
            (selection instanceof NodeSelection && selection.node.type.name === 'imageBlock')
          ) {
            editor.chain().focus().updateAttributes('imageBlock', { class: cmd.class }).run();
          }
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('imageBlock', { class: cmd.class })
            : editor.isActive('imageBlock', { class: null }) || editor.isActive('imageBlock', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('imageBlock')) {
            return false;
          }
          return true;
        },
        isActive: (editor) => {
          return editor.isActive('imageBlock');
        },
      };
    },
    file: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().uploadMediaFile().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor) => {
          const { state } = editor;
          const { selection } = state;
          if (
            editor.isActive('fileBlock') ||
            (selection instanceof NodeSelection && selection.node.type.name === 'fileBlock')
          ) {
            editor.chain().focus().updateAttributes('fileBlock', { class: cmd.class }).run();
          }
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('fileBlock', { class: cmd.class })
            : editor.isActive('fileBlock', { class: null }) || editor.isActive('fileBlock', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('fileBlock')) {
            return false;
          }
          return true;
        },
        isActive: (editor) => {
          return editor.isActive('fileBlock');
        },
      };
    },
    linkButton: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setLinkButtonBlock().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: (editor) => {
          const { state } = editor;
          const { selection } = state;
          if (
            editor.isActive('linkButton') ||
            (selection instanceof NodeSelection && selection.node.type.name === 'linkButton')
          ) {
            editor.chain().focus().updateAttributes('linkButton', { class: cmd.class }).run();
          }
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('linkButton', { class: cmd.class })
            : editor.isActive('linkButton', { class: null }) || editor.isActive('linkButton', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('linkButton')) {
            return false;
          }
          return true;
        },
        isActive: (editor) => {
          return editor.isActive('linkButton');
        },
      };
    },
    table: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run();
          const { $from } = editor.state.selection;
          // カーソル位置から上へ順に探索して `orderedList` を見つける
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'table') {
              const pos = $from.before(depth);
              editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
              break;
            }
          }
        },
        convert: (editor) => {
          const { state } = editor;
          const { selection } = state;
          if (
            editor.isActive('table') ||
            (selection instanceof NodeSelection && selection.node.type.name === 'table')
          ) {
            editor.chain().focus().updateAttributes('table', { class: cmd.class }).run();
          }
        },
        isDisabled: (editor) => {
          const isSame = cmd.class
            ? editor.isActive('table', { class: cmd.class })
            : editor.isActive('table', { class: null }) || editor.isActive('table', { class: '' });
          // 同じ 要素 + class なら disable
          if (isSame) {
            return true;
          }
          // 同じ要素だが class が違う場合 → enable
          if (editor.isActive('table')) {
            return false;
          }
          return true;
        },
        isActive: (editor) => {
          return editor.isActive('table');
        },
      };
    },
    columns2: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor
            .chain()
            .focus()
            .setColumns(ColumnLayout.TwoColumn)
            .focus(editor.state.selection.head - 1)
            .setBlockAttrs(pos, { class: cmd.class })
            .run();
        },
        convert: () => {},
        isDisabled: () => true,
        isActive: (editor) => {
          return editor.isActive('columns2');
        },
      };
    },
    columns3: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor
            .chain()
            .focus()
            .setColumns(ColumnLayout.ThreeColumn)
            .focus(editor.state.selection.head - 1)
            .setBlockAttrs(pos, { class: cmd.class })
            .run();
        },
        convert: () => {},
        isDisabled: () => true,
        isActive: (editor) => {
          return editor.isActive('columns3');
        },
      };
    },
    horizontalRule: (cmd) => {
      return {
        action: (editor) => {
          recordUsage(cmd.name);
          const { $from } = editor.state.selection;
          const pos = $from.before($from.depth);
          editor.chain().focus().setHorizontalRule().run();
          editor.chain().focus().setBlockAttrs(pos, { class: cmd.class }).run();
        },
        convert: () => {},
        isDisabled: () => true,
        isActive: (editor) => {
          return editor.isActive('horizontalRule');
        },
      };
    },
  };

  return commands
    .map((cmd) =>
      actions[cmd.name]
        ? {
            ...cmd,
            ...actions[cmd.name](cmd),
          }
        : null
    )
    .filter(Boolean) as CommandItem[];
};

interface UseBlockMenusOptions {
  blockMenus: BlockMenuItem[];
}

export interface UseBlockMenusResult {
  groups: BlockMenuItem[];
  getFilteredBlockMenus: ({ query, editor }: { query: string; editor: Editor }) => BlockMenuItem[];
}

export function useBlockMenus({ blockMenus }: UseBlockMenusOptions): UseBlockMenusResult {
  const { frequentlyUsed, recordUsage } = useFrequentlyUsed();
  const itemsRef = useRef<BlockMenuItem[]>([]);

  useEffect(() => {
    itemsRef.current = blockMenus ?? [];
  }, [blockMenus]);

  const getFilteredBlockMenus = useCallback(
    ({ query, editor }: { query: string; editor: Editor }): BlockMenuItem[] => {
      const currentItems = itemsRef.current;
      if (!currentItems || currentItems.length === 0) {
        return [];
      }
      const withFilteredCommands = currentItems.map((group: BlockMenuItem) => ({
        ...group,
        commands: group.commands
          .filter((item) => {
            const labelNormalized = item.label.toLowerCase().trim();
            const queryNormalized = query.toLowerCase().trim();
            if (item.aliases) {
              const aliases = item.aliases.map((alias) => alias.toLowerCase().trim());
              return (
                labelNormalized.includes(queryNormalized) || aliases.some((alias) => alias.includes(queryNormalized))
              );
            }
            return labelNormalized.includes(queryNormalized);
          })
          .filter((command) => {
            if (!command.shouldBeHidden) {
              return true;
            }
            return !command.shouldBeHidden(editor);
          }),
      }));
      const withoutEmptyGroups = withFilteredCommands.filter((group) => {
        if (group.commands.length > 0) {
          return true;
        }
        return false;
      });
      const withEnabledSettings = withoutEmptyGroups.map((group) => ({
        ...group,
        commands: group.commands.map((command) => ({
          ...command,
          isEnabled: true,
        })),
      }));

      const resolvedGroups = withEnabledSettings.map((group) => ({
        ...group,
        commands: resolveCommands(group.commands, recordUsage),
      }));

      // よく使うコマンドグループ
      if (frequentlyUsed.length > 0) {
        const frequentlyUsedCommands: CommandItem[] = resolvedGroups
          .flatMap((group) => group.commands)
          .filter(
            (cmd, index, self) =>
              frequentlyUsed.includes(cmd.name) && self.findIndex((c) => c.name === cmd.name) === index // 重複除去
          )
          .sort((a, b) => frequentlyUsed.indexOf(a.name) - frequentlyUsed.indexOf(b.name));

        if (frequentlyUsedCommands.length > 0) {
          const frequentlyUsedGroup: BlockMenuItem = {
            title: 'よく使うブロック',
            name: 'frequently-used',
            commands: frequentlyUsedCommands,
          };
          return [frequentlyUsedGroup, ...resolvedGroups];
        }
      }
      return resolvedGroups;
    },
    [recordUsage, frequentlyUsed]
  );
  return {
    groups: blockMenus ?? [],
    getFilteredBlockMenus,
  };
}

export function useFilteredCommands(menus: BlockMenuItem[], editor: Editor, currentPos: number): BlockMenuItem[] {
  return useMemo(() => {
    return menus
      .map((item) => {
        const filteredCommands = item.commands.filter((cmd: CommandItem) => !cmd.isDisabled(editor, currentPos));
        return {
          ...item,
          commands: filteredCommands,
        };
      })
      .filter((item) => item.commands.length > 0 && item.name !== 'frequently-used');
  }, [menus, editor, currentPos]);
}
