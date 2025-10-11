import { memo, useState } from 'react';
import { Popover, PopoverTrigger, PopoverContent } from '@components/popover';
import { LinkEditorPanel } from '@features/block-editor/components/panels';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';

export type ImageBlockLinkProps = {
  initialUrl: string;
  initialOpenInNewTab: boolean;
  onChange: (url: string, openInNewTab?: boolean) => void;
};

export const ImageBlockLink = memo(({ initialUrl, initialOpenInNewTab, onChange }: ImageBlockLinkProps) => {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <Popover modal isOpen={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Toolbar.Button
          tooltip="リンクを設定"
          type="button"
          active={!!initialUrl}
          onClick={() => setIsOpen((prev) => !prev)}
          alia-label="リンクを設定"
        >
          <Icon name="link" />
        </Toolbar.Button>
      </PopoverTrigger>
      <PopoverContent data-elevation="3">
        <LinkEditorPanel
          initialUrl={initialUrl}
          initialOpenInNewTab={initialOpenInNewTab}
          onSetLink={(link, openInNewTab) => {
            onChange(link, openInNewTab);
            setIsOpen(false);
          }}
          onClear={() => {
            onChange('', false);
            setIsOpen(false);
          }}
        />
      </PopoverContent>
    </Popover>
  );
});

ImageBlockLink.displayName = 'ImageBlockLink';
