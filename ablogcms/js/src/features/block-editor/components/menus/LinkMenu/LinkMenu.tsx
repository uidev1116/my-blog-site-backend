import { BubbleMenu as BaseBubbleMenu, BubbleMenuProps } from '@tiptap/react';
import { useCallback, useState } from 'react';

import { Popover, PopoverContent } from '@components/popover';

import { isTextSelected } from '@features/block-editor/lib/utils';
import { MenuProps } from '../types';
import { LinkPreviewPanel } from '../../panels/LinkPreviewPanel';
import { LinkEditorPanel } from '../../panels';

export const LinkMenu = ({ editor, appendTo }: MenuProps): JSX.Element => {
  const [showEdit, setShowEdit] = useState(false);
  const [isOpen, setIsOpen] = useState(false);

  const shouldShow = useCallback<NonNullable<BubbleMenuProps['shouldShow']>>(({ editor }) => {
    const isActive = editor.isActive('link');
    const isShow = isActive && !isTextSelected({ editor });
    setIsOpen(isShow);
    return isShow;
  }, []);

  const { href: link, target } = editor.getAttributes('link');

  const handleEdit = useCallback(() => {
    setShowEdit(true);
  }, []);

  const onSetLink = useCallback(
    (url: string, openInNewTab?: boolean) => {
      editor
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({
          href: url,
          target: openInNewTab ? '_blank' : null,
          rel: openInNewTab ? 'noopener noreferrer' : null,
        })
        .run();
      setShowEdit(false);
    },
    [editor]
  );

  const onUnsetLink = useCallback(() => {
    editor.chain().focus().extendMarkRange('link').unsetLink().run();
    setShowEdit(false);
    return null;
  }, [editor]);

  const getAnchorRect = useCallback(() => {
    const { view } = editor;
    const { state } = editor;
    const { $from } = state.selection;
    const pos = $from.before($from.depth);
    const dom = view.nodeDOM(pos);
    if (dom instanceof HTMLElement) {
      return dom.getBoundingClientRect();
    }
    return null;
  }, [editor]);

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey="linkMenu"
      shouldShow={shouldShow}
      updateDelay={0}
      tippyOptions={{
        placement: 'bottom-start',
        appendTo: () => appendTo?.current,
        onHidden: () => {
          setShowEdit(false);
        },
      }}
    >
      <Popover modal isOpen={isOpen} getAnchorRect={getAnchorRect}>
        <PopoverContent data-elevation="2" size={showEdit ? 'default' : 'small'}>
          {showEdit ? (
            <LinkEditorPanel
              initialUrl={link}
              initialOpenInNewTab={target === '_blank'}
              onSetLink={onSetLink}
              onClear={onUnsetLink}
            />
          ) : (
            <LinkPreviewPanel url={link} onClear={onUnsetLink} onEdit={handleEdit} />
          )}
        </PopoverContent>
      </Popover>
    </BaseBubbleMenu>
  );
};

export default LinkMenu;
