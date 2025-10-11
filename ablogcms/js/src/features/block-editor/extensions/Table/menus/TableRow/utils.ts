import { Editor } from '@tiptap/react';
import { EditorView } from '@tiptap/pm/view';
import { Table } from '../..';

export const isRowGripSelected = ({ editor, view, from }: { editor: Editor; view: EditorView; from: number }) => {
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

  const gripRow = container && container.querySelector && container.querySelector('a.grip-row.selected');

  return !!gripRow;
};

export default isRowGripSelected;
