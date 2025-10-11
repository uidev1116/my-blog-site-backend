import { EditorContent } from '@tiptap/react';
import { useRef } from 'react';
import { LinkMenu } from '@features/block-editor/components/menus';
import { SettingsProvider } from '@features/block-editor/context/EditorSettings';
import { useBlockEditor, UseBlockEditorOptions } from '@features/block-editor/hooks/useBlockEditor';
import { TableMenu, TableColumnMenu, TableRowMenu } from '@features/block-editor/extensions/Table/menus';
import { TextMenu } from '@features/block-editor/components/menus/TextMenu';
import { ContentItemMenu } from '@features/block-editor/components/menus/ContentItemMenu';
import ImageBlockMenu from '@features/block-editor/extensions/ImageBlock/components/ImageBlockMenu';
import FileBlockMenu from '@features/block-editor/extensions/FileBlock/components/FileBlockMenu';
import LinkButtonMenu from '@features/block-editor/extensions/LinkButton/components/LinkButtonMenu';
import { Tooltip } from '@components/tooltip';
import { useSettings } from '@features/block-editor/hooks/useSettings';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface BlockEditorProps extends UseBlockEditorOptions {}

export const BlockEditor = (props: BlockEditorProps) => {
  const menuContainerRef = useRef(null);
  const settings = useSettings();
  const { editor, getFilteredBlockMenus } = useBlockEditor({ ...props, blockMenus: settings.blockMenus });

  if (!editor) {
    return null;
  }

  return (
    <SettingsProvider settings={settings}>
      <div className="acms-admin-block-editor-container">
        <div className="acms-admin-block-editor" ref={menuContainerRef}>
          <EditorContent editor={editor} className="acms-admin-block-editor-content" />
          <ContentItemMenu editor={editor} getFilteredBlockMenus={getFilteredBlockMenus} />
          <LinkMenu editor={editor} appendTo={menuContainerRef} />
          <TextMenu editor={editor} appendTo={menuContainerRef} />
          <TableMenu editor={editor} appendTo={menuContainerRef} />
          <TableRowMenu editor={editor} appendTo={menuContainerRef} />
          <TableColumnMenu editor={editor} appendTo={menuContainerRef} />
          <ImageBlockMenu editor={editor} appendTo={menuContainerRef} />
          <FileBlockMenu editor={editor} appendTo={menuContainerRef} />
          <LinkButtonMenu editor={editor} appendTo={menuContainerRef} />
        </div>
      </div>
      <Tooltip id="block-editor-shared-tooltip" delayShow={150} />
    </SettingsProvider>
  );
};

export default BlockEditor;
