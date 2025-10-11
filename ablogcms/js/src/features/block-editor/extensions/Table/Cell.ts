import { mergeAttributes, Node } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

import { getCellsInColumn, isRowSelected, selectRow } from './utils';

export interface TableCellOptions {
  HTMLAttributes: Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    tableCell: {
      setCellBackgroundColor: (color: string) => ReturnType;
    };
  }
}

export const TableCell = Node.create<TableCellOptions>({
  name: 'tableCell',

  content: 'block+', // TODO: Do not allow table in table

  tableRole: 'cell',

  isolating: true,

  addOptions() {
    return {
      HTMLAttributes: {},
    };
  },

  parseHTML() {
    return [{ tag: 'td' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['td', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0];
  },

  addAttributes() {
    return {
      colspan: {
        default: 1,
        parseHTML: (element) => {
          const colspan = element.getAttribute('colspan');
          const value = colspan ? parseInt(colspan, 10) : 1;

          return value;
        },
      },
      rowspan: {
        default: 1,
        parseHTML: (element) => {
          const rowspan = element.getAttribute('rowspan');
          const value = rowspan ? parseInt(rowspan, 10) : 1;

          return value;
        },
      },
      colwidth: {
        default: null,
        parseHTML: (element) => {
          const colwidth = element.getAttribute('colwidth');
          const value = colwidth ? [parseInt(colwidth, 10)] : null;

          return value;
        },
      },
      backgroundColor: {
        default: null,
        parseHTML: (element) => {
          const style = element.getAttribute('style');
          if (!style) return null;

          const match = style.match(/background-color:\s*([^;]+)/i);
          return match ? match[1] : null;
        },
        renderHTML: (attributes) => {
          if (!attributes.backgroundColor) return {};
          return {
            style: `background-color: ${attributes.backgroundColor}`,
          };
        },
      },
    };
  },

  addCommands() {
    return {
      setCellBackgroundColor:
        (color: string | null) =>
        ({ commands }) => {
          return commands.updateAttributes('tableCell', {
            backgroundColor: color,
          });
        },
    };
  },

  addProseMirrorPlugins() {
    const { isEditable } = this.editor;

    return [
      new Plugin({
        props: {
          decorations: (state) => {
            if (!isEditable) {
              return DecorationSet.empty;
            }

            const { doc, selection } = state;
            const decorations: Decoration[] = [];
            const cells = getCellsInColumn(0)(selection);

            if (cells) {
              cells.forEach(({ pos }: { pos: number }, index: number) => {
                decorations.push(
                  Decoration.widget(pos + 1, () => {
                    const rowSelected = isRowSelected(index)(selection);
                    let className = 'grip-row';

                    if (rowSelected) {
                      className += ' selected';
                    }

                    if (index === 0) {
                      className += ' first';
                    }

                    if (index === cells.length - 1) {
                      className += ' last';
                    }

                    const grip = document.createElement('a');
                    const icon = document.createElement('span');
                    icon.className = 'material-symbols-outlined acms-admin-block-editor-icon grip-icon';
                    icon.textContent = 'more_vert';
                    icon.setAttribute('aria-hidden', 'true');
                    grip.appendChild(icon);

                    grip.className = className;
                    grip.addEventListener('mousedown', (event) => {
                      event.preventDefault();
                      event.stopImmediatePropagation();

                      this.editor.view.dispatch(selectRow(index)(this.editor.state.tr));
                    });

                    return grip;
                  })
                );
              });
            }

            return DecorationSet.create(doc, decorations);
          },
        },
      }),
    ];
  },
});
