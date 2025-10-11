import { Extension } from '@tiptap/core';
import { Plugin, TextSelection } from '@tiptap/pm/state';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    blockAttributes: {
      setBlockAttrs: (pos: number, attrs: { id?: string | null; class?: string | null }) => ReturnType;
    };
  }
}

export const BlockAttributes = Extension.create({
  name: 'blockAttributes',

  addGlobalAttributes() {
    return [
      {
        types: [
          'heading',
          'paragraph',
          'bulletList',
          'orderedList',
          'MediaUpload',
          'imageBlock',
          'fileBlock',
          'linkButton',
          'table',
          'blockquote',
          'codeBlock',
          'bulletList',
          'orderedList',
          'horizontalRule',
          'columns',
        ],
        attributes: {
          id: {
            default: null,
            parseHTML: (element) => element.getAttribute('id'),
            renderHTML: (attributes) => (attributes.id ? { id: attributes.id } : {}),
            keepOnSplit: false, // コピー＆ペースト時に属性を保持しない
          },
          class: {
            default: null,
            parseHTML: (element) => element.getAttribute('class'),
            renderHTML: (attributes) => (attributes.class ? { class: attributes.class } : {}),
          },
        },
      },
    ];
  },

  addCommands() {
    return {
      // ノードの属性を設定する
      setBlockAttrs:
        (pos, attrs) =>
        ({ state, dispatch }) => {
          const { tr, selection } = state;
          const { $from } = selection;
          let targetPos;
          if (typeof pos === 'number') {
            targetPos = pos;
          } else if ($from.depth > 0) {
            targetPos = $from.before(1);
          } else {
            targetPos = null;
          }
          if (targetPos === null) return false;
          const node = state.doc.nodeAt(targetPos);
          if (!node || !dispatch) return false;
          if (node && dispatch) {
            dispatch(tr.setNodeMarkup(targetPos, undefined, { ...node.attrs, ...attrs }));
            return true;
          }
          return false;
        },
    };
  },

  addProseMirrorPlugins() {
    const isMac = typeof navigator !== 'undefined' && /Mac/.test(navigator.platform);

    return [
      new Plugin({
        props: {
          handlePaste(view, event) {
            const { state, dispatch } = view;
            const { selection } = state;
            const { parent } = selection.$from;

            // codeBlock 内であれば、HTML をそのままテキストで貼り付ける
            if (parent.type.name === 'codeBlock') {
              const text = event.clipboardData?.getData('text/plain');
              if (text) {
                dispatch(state.tr.insertText(text, selection.from, selection.to));
                return true;
              }
            }

            return false;
          },

          handleKeyDown(view, event) {
            const { state, dispatch } = view;
            const { selection } = state;
            const { $from } = selection;

            if ($from.parent.type.name !== 'codeBlock') return false;

            // ⌘A (Mac) or Ctrl+A (Windows) → 全選択
            if ((event.metaKey && event.key === 'a') || (!isMac && event.ctrlKey && event.key === 'a')) {
              event.preventDefault();
              const start = $from.start();
              const end = $from.end();
              dispatch(state.tr.setSelection(TextSelection.create(state.doc, start, end)));
              return true;
            }

            // Ctrl+A → 行頭移動（Mac のみ）
            if (isMac && event.ctrlKey && event.key === 'a') {
              event.preventDefault();
              const pos = selection.from;
              const textBefore = $from.parent.textContent.slice(0, $from.parentOffset);
              const offsetToLineStart = textBefore.lastIndexOf('\n') + 1; // 行頭なら 0、改行直後なら n+1
              const newPos = pos - ($from.parentOffset - offsetToLineStart);
              dispatch(state.tr.setSelection(TextSelection.create(state.doc, newPos)));
              return true;
            }

            // Ctrl+E → 行末移動（Mac のみ）
            if (isMac && event.ctrlKey && event.key === 'e') {
              event.preventDefault();
              const pos = selection.from;
              const parentText = $from.parent.textContent;
              const offsetToLineEnd = parentText.indexOf('\n', $from.parentOffset);
              const lineEndOffset = offsetToLineEnd === -1 ? parentText.length : offsetToLineEnd;
              const lineEndPos = pos + (lineEndOffset - $from.parentOffset);
              dispatch(state.tr.setSelection(TextSelection.create(state.doc, lineEndPos)));
              return true;
            }

            return false;
          },
        },
      }),
    ];
  },
});
