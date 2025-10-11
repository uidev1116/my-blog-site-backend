import TiptapTableHeader from '@tiptap/extension-table-header';
import { Plugin } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

import { getCellsInRow, isColumnSelected, selectColumn } from './utils';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    tableHeader: {
      setHeaderBackgroundColor: (color: string) => ReturnType;
    };
  }
}

export const TableHeader = TiptapTableHeader.extend({
  addAttributes() {
    return {
      colspan: {
        default: 1,
      },
      rowspan: {
        default: 1,
      },
      colwidth: {
        default: null,
        parseHTML: (element) => {
          const colwidth = element.getAttribute('colwidth');
          const value = colwidth ? colwidth.split(',').map((item) => parseInt(item, 10)) : null;

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
      setHeaderBackgroundColor:
        (color: string | null) =>
        ({ commands }) => {
          return commands.updateAttributes('tableHeader', {
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
            const cells = getCellsInRow(0)(selection);

            if (cells) {
              cells.forEach(({ pos }: { pos: number }, index: number) => {
                decorations.push(
                  Decoration.widget(pos + 1, () => {
                    const colSelected = isColumnSelected(index)(selection);
                    let className = 'grip-column';

                    if (colSelected) {
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
                    icon.textContent = 'more_horiz';
                    icon.setAttribute('aria-hidden', 'true');
                    grip.appendChild(icon);

                    grip.className = className;
                    grip.addEventListener('mousedown', (event) => {
                      event.preventDefault();
                      event.stopImmediatePropagation();

                      this.editor.view.dispatch(selectColumn(index)(this.editor.state.tr));
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

export default TableHeader;
