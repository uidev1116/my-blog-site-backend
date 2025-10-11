import { Editor } from '@tiptap/react';
import { EditorView } from '@tiptap/pm/view';
import { Table } from '../..';

export const isColumnGripSelected = ({ editor, view, from }: { editor: Editor; view: EditorView; from: number }) => {
  const domAtPos = view.domAtPos(from).node as HTMLElement;
  const nodeDOM = view.nodeDOM(from) as HTMLElement;
  const node = nodeDOM || domAtPos;

  if (!editor.isActive(Table.name) || !node) {
    return false;
  }

  let container = node;

  while (container && !['TD', 'TH'].includes(container.tagName)) {
    container = container.parentElement!;
  }

  const gripColumn = container && container.querySelector && container.querySelector('a.grip-column.selected');

  return !!gripColumn;
};

export default isColumnGripSelected;
