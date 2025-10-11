import { Node } from '@tiptap/pm/model';
import { Editor } from '@tiptap/react';
import { NodeSelection } from '@tiptap/pm/state';
import { useCallback } from 'react';

const useContentItemActions = (editor: Editor, currentNode: Node | null, currentNodePos: number) => {
  const getCurrentNode = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { state } = editor;
      const { selection } = state;
      const { $from } = selection;

      let curNode = curNodeProp ?? null;
      let curPos = curNodePosProp ?? null;

      if (!curNode || !curPos) {
        if (selection instanceof NodeSelection) {
          curNode = selection.node;
          curPos = selection.from;
        } else {
          const depth = $from.depth >= 1 ? 1 : $from.depth;
          curNode = $from.node(depth);
          if (depth > 0) {
            curPos = $from.before(depth);
          } else {
            return {
              curNode: null,
              curPos: -1,
            };
          }
        }
      }
      return {
        curNode,
        curPos,
      };
    },
    [editor]
  );

  const resetTextFormatting = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { curNode, curPos } = getCurrentNode(curNodeProp, curNodePosProp);
      if (!curNode || curPos === -1) return;

      const chain = editor.chain();
      chain.setBlockAttrs(curPos, { id: null, class: null });
      chain.setNodeSelection(curPos).unsetAllMarks();
      if (curNode?.type.name !== 'paragraph') {
        chain.focus().setParagraph();
      }
      chain.run();
    },
    [editor, getCurrentNode]
  );

  const duplicateNode = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { curNode, curPos } = getCurrentNode(curNodeProp, curNodePosProp);
      if (!curNode || curPos === -1) return;

      const nodeJSON = curNode.toJSON();

      editor
        .chain()
        .setMeta('hideDragHandle', true)
        .insertContentAt(curPos + (curNode?.nodeSize || 0), {
          type: nodeJSON.type,
          attrs: { ...nodeJSON.attrs, id: null }, // ID属性を削除
          content: nodeJSON.content || [],
        })
        // .setTextSelection(curPos + (curNode?.nodeSize || 0) + 1)
        .run();
    },
    [editor, getCurrentNode]
  );

  const copyNodeToClipboard = useCallback(() => {
    const nodeText = currentNode?.textContent || '';

    navigator.clipboard.writeText(nodeText).catch((err) => {
      // eslint-disable-next-line no-console
      console.error('Clipboard copy failed:', err);
    });
  }, [currentNode]);

  const deleteNode = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { curNode, curPos } = getCurrentNode(curNodeProp, curNodePosProp);
      if (!curNode || curPos === -1) return;

      editor.chain().setMeta('hideDragHandle', true).setNodeSelection(curPos).deleteSelection().run();
    },
    [editor, getCurrentNode]
  );

  const handleAdd = useCallback(() => {
    if (currentNodePos !== -1) {
      const currentNodeSize = currentNode?.nodeSize || 0;
      const insertPos = currentNodePos + currentNodeSize;
      const currentNodeIsEmptyParagraph = currentNode?.type.name === 'paragraph' && currentNode?.content?.size === 0;
      const focusPos = currentNodeIsEmptyParagraph ? currentNodePos + 2 : insertPos + 2;

      editor
        .chain()
        .command(({ dispatch, tr, state }) => {
          if (dispatch) {
            if (currentNodeIsEmptyParagraph) {
              tr.insertText('/', currentNodePos, currentNodePos + 1);
            } else {
              tr.insert(insertPos, state.schema.nodes.paragraph.create(null, [state.schema.text('/')]));
            }

            return dispatch(tr);
          }

          return true;
        })
        .focus(focusPos)
        .run();
    }
  }, [currentNode, currentNodePos, editor]);

  const setHeadingId = useCallback(
    (id: string) => {
      editor.chain().setBlockAttrs(currentNodePos, { id }).run();
    },
    [currentNodePos, editor]
  );

  const setBlockAttrs = useCallback(
    (attrs: { id: string; class: string }) => {
      editor.chain().setBlockAttrs(currentNodePos, attrs).run();
    },
    [currentNodePos, editor]
  );

  const moveBlockUp = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { state } = editor;
      const { $from } = state.selection;
      const { curNode, curPos } = getCurrentNode(curNodeProp, curNodePosProp);
      if (!curNode || curPos === -1) return;

      const { nodeSize } = curNode;
      const index = $from.index(0);
      if (index === 0) return;

      const prevNode = state.doc.child(index - 1);
      if (!prevNode) return;

      const nodeJSON = curNode.toJSON();

      editor
        .chain()
        .deleteRange({ from: curPos, to: curPos + nodeSize })
        .insertContentAt(curPos - prevNode.nodeSize, nodeJSON)
        .run();
    },
    [editor, getCurrentNode]
  );

  const moveBlockDown = useCallback(
    (curNodeProp?: Node | null, curNodePosProp?: number) => {
      const { state } = editor;
      const { curNode, curPos } = getCurrentNode(curNodeProp, curNodePosProp);
      if (!curNode || curPos === -1) return;

      const { nodeSize } = curNode;
      const nextPos = curPos + nodeSize;
      const nextNode = state.doc.resolve(nextPos).nodeAfter;
      if (!nextNode) return;

      const nextNodeSize = nextNode.nodeSize;
      const nodeJSON = curNode.toJSON();

      editor
        .chain()
        .deleteRange({ from: curPos, to: curPos + nodeSize })
        .insertContentAt(curPos + nextNodeSize, nodeJSON)
        .run();
    },
    [editor, getCurrentNode]
  );

  return {
    resetTextFormatting,
    duplicateNode,
    copyNodeToClipboard,
    deleteNode,
    handleAdd,
    setHeadingId,
    setBlockAttrs,
    moveBlockUp,
    moveBlockDown,
  };
};

export default useContentItemActions;
