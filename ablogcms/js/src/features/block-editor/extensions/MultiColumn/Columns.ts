import { Node, mergeAttributes } from '@tiptap/core';

export enum ColumnLayout {
  OneColumn = 'one-column',
  TwoColumn = 'two-column',
  ThreeColumn = 'three-column',
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    columns: {
      setColumns: (layout?: ColumnLayout) => ReturnType;
      setLayout: (layout: ColumnLayout) => ReturnType;
    };
  }
}

export const Columns = Node.create({
  name: 'columns',

  group: 'columns',

  // 1-3カラムまで対応
  content: 'column column? column?',

  defining: true,

  isolating: true,

  addAttributes() {
    return {
      layout: {
        default: ColumnLayout.TwoColumn,
        parseHTML: (element) => element.getAttribute('data-layout'),
        renderHTML: (attributes) => ({
          'data-layout': attributes.layout,
        }),
      },
    };
  },

  addCommands() {
    return {
      setColumns:
        (layout = ColumnLayout.TwoColumn) =>
        ({ commands }) => {
          let count = 2;
          if (layout === ColumnLayout.OneColumn) {
            count = 1;
          } else if (layout === ColumnLayout.ThreeColumn) {
            count = 3;
          }
          const columns = Array.from({ length: count }, () => '<div data-type="column"><p></p></div>');

          return commands.insertContent(`<div data-type="columns" data-layout="${layout}">${columns.join('')}</div>`);
        },
      setLayout:
        (layout: ColumnLayout) =>
        ({ commands }) =>
          commands.updateAttributes('columns', { layout }),
    };
  },

  renderHTML({ HTMLAttributes }) {
    const layout = HTMLAttributes['data-layout'] || ColumnLayout.TwoColumn;

    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        id: HTMLAttributes.id,
        class: `layout-${layout}`,
        'data-type': 'columns',
        'data-layout': layout,
      }),
      0,
    ];
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-type="columns"]',
      },
    ];
  },
});

export default Columns;
