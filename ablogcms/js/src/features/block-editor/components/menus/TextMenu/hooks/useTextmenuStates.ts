import { Editor } from '@tiptap/react';
import { useCallback } from 'react';
import { isCellSelection } from '@features/block-editor/extensions/Table/utils';
import { ShouldShowProps } from '../../types';
import { isCustomNodeSelected, isTextSelected } from '../../../../lib/utils';

export const useTextmenuStates = (editor: Editor) => {
  const shouldShow = useCallback(
    ({ view, state, from }: ShouldShowProps) => {
      if (!view || !state) {
        return false;
      }

      // セル選択の場合は、テーブルメニューを優先するため非表示にする
      if (isCellSelection(state.selection)) {
        return false;
      }

      const domAtPos = view.domAtPos(from || 0).node as HTMLElement;
      const nodeDOM = view.nodeDOM(from || 0) as HTMLElement;
      const node = nodeDOM || domAtPos;

      if (isCustomNodeSelected(editor, node)) {
        return false;
      }

      return isTextSelected({ editor });
    },
    [editor]
  );

  return {
    isLink: editor.isActive('link'),
    isBold: editor.isActive('bold'),
    isItalic: editor.isActive('italic'),
    isStrike: editor.isActive('strike'),
    isUnderline: editor.isActive('underline'),
    isCode: editor.isActive('code'),
    isSubscript: editor.isActive('subscript'),
    isSuperscript: editor.isActive('superscript'),
    isAlignLeft: editor.isActive({ textAlign: 'left' }),
    isAlignCenter: editor.isActive({ textAlign: 'center' }),
    isAlignRight: editor.isActive({ textAlign: 'right' }),
    isAlignJustify: editor.isActive({ textAlign: 'justify' }),
    currentColor: editor.getAttributes('textStyle')?.color || undefined,
    currentHighlight: editor.getAttributes('highlight')?.color || undefined,
    currentFont: editor.getAttributes('textStyle')?.fontFamily || undefined,
    currentSize: editor.getAttributes('textStyle')?.fontSize || undefined,
    currentCustomClass: editor.getAttributes('customMark')?.class || undefined,
    shouldShow,
  };
};
