import { type Editor } from '@tiptap/core';
import { Popover, PopoverTrigger, PopoverContent, type PopoverRef } from '@components/popover';
import { LinkEditorPanel } from '@features/block-editor/components/panels';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { useCallback, useRef } from 'react';

export type EditLinkPopoverProps = {
  editor: Editor;
  onSetLink: (link: string, openInNewTab?: boolean) => void;
  onClearLink: () => void;
  isActive: boolean;
};

export const EditLinkPopover = ({ editor, onSetLink, onClearLink, isActive }: EditLinkPopoverProps) => {
  const popoverRef = useRef<PopoverRef>(null);
  const { href: link, target } = editor.getAttributes('link');
  const handleSetLink = useCallback(
    (link: string, openInNewTab?: boolean) => {
      onSetLink(link, openInNewTab);
      popoverRef.current?.closePopover();
    },
    [onSetLink]
  );

  const handleClearLink = useCallback(() => {
    onClearLink();
    popoverRef.current?.closePopover();
  }, [onClearLink]);

  return (
    <Popover modal ref={popoverRef}>
      <PopoverTrigger asChild>
        <Toolbar.Button type="button" tooltip="リンク" aria-label="リンクを設定" active={isActive}>
          <Icon name="link" />
        </Toolbar.Button>
      </PopoverTrigger>
      <PopoverContent data-elevation="2">
        <LinkEditorPanel
          initialUrl={link}
          initialOpenInNewTab={target === '_blank'}
          onSetLink={handleSetLink}
          onClear={handleClearLink}
        />
      </PopoverContent>
    </Popover>
  );
};
