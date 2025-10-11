import { useCallback } from 'react';
import { Editor } from '@tiptap/react';

export const useTableStyle = (editor: Editor) => {
  const handleSetBgColor = useCallback(
    (color: string) => {
      editor.chain().setCellBackgroundColor(color).run();
      editor.chain().setHeaderBackgroundColor(color).run();
    },
    [editor]
  );

  const handleClearBgColor = useCallback(() => {
    editor.chain().setCellBackgroundColor('').run();
    editor.chain().setHeaderBackgroundColor('').run();
  }, [editor]);

  return {
    handleSetBgColor,
    handleClearBgColor,
  };
};
