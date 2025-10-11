import { Table as TiptapTable } from '@tiptap/extension-table';
import { mergeAttributes } from '@tiptap/core';
import type { DOMOutputSpec } from 'prosemirror-model';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    tableBlock: {
      toggleTableScrollable: () => ReturnType;
    };
  }
}

export const Table = TiptapTable.extend({
  addAttributes() {
    return {
      scrollable: {
        default: true,
        parseHTML: (element) => {
          return !!element.getAttribute('data-scrollable');
        },
        renderHTML: (attributes) => {
          return {
            'data-scrollable': attributes.scrollable ? true : null,
          };
        },
      },
    };
  },
  renderHTML({ HTMLAttributes }): DOMOutputSpec {
    const scrollable = HTMLAttributes['data-scrollable'];
    const scrollableWrapperClass = scrollable ? ACMS.Config.blockEditorConfig.tableScrollableWrapperClass : '';
    const scrollableTableClass = ACMS.Config.blockEditorConfig.tableScrollableClass || '';
    const tableAttrs = mergeAttributes(this.options.HTMLAttributes, HTMLAttributes);
    // class属性から scrollHintClass を除外し、必要に応じて再追加
    const originalClass = tableAttrs.class || '';
    const classList = originalClass.split(/\s+/).filter((cls: string) => cls && cls !== scrollableTableClass); // 対象のクラスを除去

    if (scrollable) {
      classList.push(scrollableTableClass);
    }
    tableAttrs.class = classList.join(' ');

    return ['div', { class: `tableWrapper ${scrollableWrapperClass}`.trim() }, ['table', tableAttrs, ['tbody', 0]]];
  },

  addCommands() {
    return {
      ...this.parent?.(),
      toggleTableScrollable:
        () =>
        ({ commands, editor }) => {
          const { state } = editor;
          const { from, to } = state.selection;

          let found = false;

          state.doc.nodesBetween(from, to, (node) => {
            if (node.type.name === 'table') {
              found = true;
              const { scrollable } = node.attrs;
              commands.updateAttributes('table', { scrollable: !scrollable });
              return false; // stop recursing
            }
          });
          return found;
        },
    };
  },
}).configure({
  resizable: false,
});

export default Table;
