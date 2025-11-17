import { Extension } from '@tiptap/core';
import { Node as ProseMirrorNode, NodeType } from '@tiptap/pm/model';
import { TextSelection } from '@tiptap/pm/state';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    unwrapList: {
      removeList: (pos: number) => ReturnType;
      removeListAsParagraphs: (pos: number) => ReturnType;
      unwrapList: () => ReturnType;
      removeBlockquote: (pos: number) => ReturnType;
      splitBulletList: (pos: number) => ReturnType;
      splitOrderedList: (pos: number) => ReturnType;
    };
  }
}

const collectParagraphTexts = (node: ProseMirrorNode, paragraphType: NodeType): string[] => {
  const texts: string[] = [];

  if (node.type === paragraphType) {
    texts.push(node.textContent);
  }
  node.forEach((child) => {
    texts.push(...collectParagraphTexts(child, paragraphType));
  });
  return texts;
};

export const CustomConvertBlock = Extension.create({
  name: 'CustomConvertBlock',

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
            newParagraphs.push(...collectParagraphTexts(listItemNode, paragraph));
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
      removeListAsParagraphs:
        (pos: number) =>
        ({ state, dispatch }) => {
          const { doc, schema } = state;
          const { bulletList, orderedList, paragraph } = schema.nodes;

          const listNode = doc.nodeAt(pos);
          if (!listNode || (listNode.type !== bulletList && listNode.type !== orderedList)) {
            return false;
          }

          const paragraphs: ProseMirrorNode[] = []; // @typescript-eslint/no-explicit-any

          listNode.forEach((listItemNode) => {
            // listItem 内の子ノード（通常は paragraph や他ブロック）
            listItemNode.forEach((child) => {
              if (child.type === paragraph) {
                const text = child.textContent;

                // 改行を含む場合は split して複数 paragraph にする
                const lines = text.split(/\r?\n/);
                lines.forEach((line) => {
                  paragraphs.push(paragraph.create({}, schema.text(line)));
                });
              } else {
                // paragraph 以外（例: heading, image など）もそのまま展開
                paragraphs.push(child);
              }
            });
          });

          if (paragraphs.length === 0) {
            return false;
          }
          const tr = state.tr.replaceWith(pos, pos + listNode.nodeSize, paragraphs);

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
      removeBlockquote:
        (pos: number) =>
        ({ state, dispatch }) => {
          const { doc, schema } = state;
          const { blockquote } = schema.nodes;

          // pos のノードを取得
          const node = doc.nodeAt(pos);
          if (!node || node.type !== blockquote) {
            return false;
          }
          // 子ノードをそのまま使う（paragraph 以外も含めて展開）
          const { content } = node;
          // blockquote を中身で置き換え
          const tr = state.tr.replaceWith(pos, pos + node.nodeSize, content);
          if (dispatch && tr.docChanged) {
            dispatch(tr);
            return true;
          }
          return false;
        },
      splitBulletList:
        (pos: number) =>
        ({ state, dispatch }) => {
          const { doc, schema } = state;
          const { bulletList, listItem, paragraph, hardBreak } = schema.nodes;
          const node = doc.nodeAt(pos);
          if (!node || node.type !== paragraph) {
            return false;
          }
          // paragraph 内を走査して <br> (= hardBreak) ごとに分割
          const items: ProseMirrorNode[][] = [[]];
          node.content.forEach((child) => {
            if (child.type === hardBreak) {
              // <br> が来たら新しい listItem の開始
              items.push([]);
            } else {
              // それ以外のノードを現在の listItem に追加
              items[items.length - 1].push(child);
            }
          });
          // listItem ノードを組み立て
          const listItems: ProseMirrorNode[] = items
            .filter((content) => content.length > 0) // 空行は無視
            .map((content) => listItem.create({}, paragraph.create({}, content)));
          if (listItems.length === 0) {
            return false;
          }
          const newList = bulletList.create({}, listItems);
          const tr = state.tr.replaceWith(pos, pos + node.nodeSize, newList);
          // bulletList の先頭に選択を置き直す
          const selection = TextSelection.near(tr.doc.resolve(pos + 1));

          if (dispatch && tr.docChanged) {
            dispatch(tr.setSelection(selection));
            return true;
          }
          return false;
        },
      splitOrderedList:
        (pos: number) =>
        ({ state, dispatch }) => {
          const { doc, schema } = state;
          const { orderedList, listItem, paragraph, hardBreak } = schema.nodes;
          const node = doc.nodeAt(pos);
          if (!node || node.type !== paragraph) {
            return false;
          }
          // paragraph 内を走査して <br> (= hardBreak) ごとに分割
          const items: ProseMirrorNode[][] = [[]];
          node.content.forEach((child) => {
            if (child.type === hardBreak) {
              // <br> が来たら新しい listItem の開始
              items.push([]);
            } else {
              // それ以外のノードを現在の listItem に追加
              items[items.length - 1].push(child);
            }
          });
          // listItem ノードを組み立て
          const listItems: ProseMirrorNode[] = items
            .filter((content) => content.length > 0) // 空行は無視
            .map((content) => listItem.create({}, paragraph.create({}, content)));
          if (listItems.length === 0) {
            return false;
          }
          const newList = orderedList.create({}, listItems);
          const tr = state.tr.replaceWith(pos, pos + node.nodeSize, newList);
          // orderedList の先頭に選択を置き直す
          const selection = TextSelection.near(tr.doc.resolve(pos + 1));

          if (dispatch && tr.docChanged) {
            dispatch(tr.setSelection(selection));
            return true;
          }
          return false;
        },
    };
  },
});
