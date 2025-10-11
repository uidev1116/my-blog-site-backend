import { Editor } from '@tiptap/react';

import {
  Figcaption,
  HorizontalRule,
  ImageBlock,
  FileBlock,
  LinkButton,
  MediaUpload,
  CodeBlock,
} from '../../extensions';

export const isTableGripSelected = (node: HTMLElement) => {
  let container = node;

  while (container && !['TD', 'TH'].includes(container.tagName)) {
    container = container.parentElement!;
  }

  const gripColumn = container && container.querySelector && container.querySelector('a.grip-column.selected');
  const gripRow = container && container.querySelector && container.querySelector('a.grip-row.selected');

  if (gripColumn || gripRow) {
    return true;
  }

  return false;
};

export const isCustomNodeSelected = (editor: Editor, node: HTMLElement) => {
  const customNodes = [
    HorizontalRule.name,
    ImageBlock.name,
    MediaUpload.name,
    CodeBlock.name,
    ImageBlock.name,
    FileBlock.name,
    LinkButton.name,
    // Link.name,
    Figcaption.name,
  ];

  return customNodes.some((type) => editor.isActive(type)) || isTableGripSelected(node);
};

export default isCustomNodeSelected;
