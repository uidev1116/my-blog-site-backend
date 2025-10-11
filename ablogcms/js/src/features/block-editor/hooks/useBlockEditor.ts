import { type Editor, type EditorOptions, useEditor } from '@tiptap/react';
import { useMemo, useRef } from 'react';
import { useBlockMenus } from '@features/block-editor/hooks/useBlockMenus';
import { BlockAttributes } from '@features/block-editor/extensions/BaseBlock';
import { SlashCommand } from '@features/block-editor/extensions';
import { ExtensionKit } from '@features/block-editor/extensions/extension-kit';
import type { BlockMenuItem } from '../types';

export interface UseBlockEditorOptions
  extends Partial<
    Pick<
      EditorOptions,
      | 'autofocus'
      | 'editorProps'
      | 'enableContentCheck'
      | 'emitContentError'
      | 'onCreate'
      | 'onContentError'
      | 'onSelectionUpdate'
      | 'onTransaction'
      | 'onFocus'
      | 'onBlur'
      | 'onDestroy'
      | 'onPaste'
      | 'onDrop'
    >
  > {
  defaultValue?: string;
  onUpdate: (value: string) => void;
  blockMenus?: BlockMenuItem[];
}

export const useBlockEditor = ({
  defaultValue = '',
  onUpdate = () => {},
  blockMenus = [],
  ...editorOptions
}: UseBlockEditorOptions) => {
  const { getFilteredBlockMenus } = useBlockMenus({ blockMenus });
  const getItemsRef = useRef(getFilteredBlockMenus);
  getItemsRef.current = getFilteredBlockMenus;

  const extensions = useMemo(
    () => [
      ...ExtensionKit(),
      BlockAttributes,
      SlashCommand.configure({
        getItems: ({ query, editor }: { query: string; editor: Editor }) => getItemsRef.current({ query, editor }),
      }),
    ],
    []
  );

  const editor = useEditor(
    {
      autofocus: false,
      onCreate: ({ editor }) => {
        if (editor.isEmpty) {
          editor.commands.setContent(defaultValue || '');
        }
      },
      onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        // send the content to an API here
        onUpdate(html);
      },
      extensions,
      ...editorOptions,
      editorProps: {
        ...editorOptions.editorProps,
        attributes: (...args) => {
          const attributes = {
            autocomplete: 'off',
            autocorrect: 'off',
            autocapitalize: 'off',
          };

          const overrideAttributes =
            typeof editorOptions.editorProps?.attributes === 'function'
              ? editorOptions.editorProps.attributes(...args)
              : editorOptions.editorProps?.attributes;

          return {
            ...attributes,
            ...overrideAttributes,
          };
        },
      },
    },
    []
  );

  return { editor, getFilteredBlockMenus };
};
