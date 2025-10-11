import { Node, mergeAttributes } from '@tiptap/core';

export enum ColumnLayout {
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

  content: 'column column column?',

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
          const isThree = layout === ColumnLayout.ThreeColumn;
          const columns = isThree
            ? [
                '<div data-type="column"><p></p></div>',
                '<div data-type="column"><p></p></div>',
                '<div data-type="column"><p></p></div>',
              ]
            : ['<div data-type="column"><p></p></div>', '<div data-type="column"><p></p></div>'];

          return commands.insertContent(`<div data-type="columns" data-layout="${layout}">${columns.join('')}</div>`);
        },
      setLayout:
        (layout: ColumnLayout) =>
        ({ commands }) =>
          commands.updateAttributes('columns', { layout }),
    };
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        id: HTMLAttributes.id,
        class: `layout-${HTMLAttributes['data-layout'] || ColumnLayout.TwoColumn}`,
        'data-type': 'columns',
        'data-layout': HTMLAttributes['data-layout'] || ColumnLayout.TwoColumn,
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
