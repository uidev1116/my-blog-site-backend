import { Extension } from '@tiptap/core';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    unwrapList: {
      removeList: (pos: number) => ReturnType;
      unwrapList: () => ReturnType;
    };
  }
}

export const UnwrapList = Extension.create({
  name: 'unwrapList',

  addCommands() {
    return {
      removeList:
        (pos: number) =>
        ({ state, dispatch }) => {
          const { doc, schema } = state;
          const { bulletList, orderedList, paragraph, hardBreak } = schema.nodes;
          const newParagraphs: string[] = [];
          const listNode = doc.nodeAt(pos);
          if (!listNode || (listNode.type !== bulletList && listNode.type !== orderedList)) {
            return false;
          }
          listNode.forEach((listItemNode) => {
            const para = listItemNode.content.firstChild;
            if (para?.type === paragraph) {
              newParagraphs.push(para.textContent);
            }
          });
          const textNodes = newParagraphs.flatMap((line, index, arr) => {
            const nodes = [schema.text(line)];
            if (index < arr.length - 1) {
              nodes.push(hardBreak.create());
            }
            return nodes;
          });
          const paragraphNode = paragraph.create({}, textNodes);
          const tr = state.tr.replaceWith(pos, pos + listNode.nodeSize, paragraphNode);
          if (dispatch && tr.docChanged) {
            dispatch(tr);
            return true;
          }
          return false;
        },
      unwrapList:
        () =>
        ({ state, dispatch }) => {
          const { selection, tr } = state;
          const { $from } = selection;
          let lifted = false;
          for (let { depth } = $from; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === 'listItem') {
              const pos = $from.before(depth);
              tr.setNodeMarkup(pos, state.schema.nodes.paragraph); // li -> p
              lifted = true;
              break;
            }
          }
          if (lifted && dispatch) {
            dispatch(tr);
            return true;
          }
          return false;
        },
    };
  },
});
