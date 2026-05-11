import { memo, useRef } from 'react';
import { Popover, PopoverTrigger, PopoverContent, PopoverClose } from '@components/popover';
import { ButtonV2 } from '@components/button-v2';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';

export type ImageBlockCaptionProps = {
  caption: string;
  alt: string;
  onChange: (caption: string, alt: string) => void;
};

export const ImageBlockCaption = memo(({ caption, alt, onChange }: ImageBlockCaptionProps) => {
  const captionRef = useRef<HTMLInputElement>(null);
  const altRef = useRef<HTMLTextAreaElement>(null);

  return (
    <Popover modal>
      <PopoverTrigger asChild>
        <Toolbar.Button
          tooltip="キャプションを編集"
          type="button"
          active={!!caption || !!alt}
          aria-label="キャプションを編集"
        >
          <Icon name="closed_caption" />
        </Toolbar.Button>
      </PopoverTrigger>
      <PopoverContent data-elevation="3">
        <form
          className="acms-admin-form acms-admin-block-editor-popover-form"
          onSubmit={() => onChange(captionRef.current?.value ?? '', altRef.current?.value ?? '')}
        >
          <div className="acms-admin-block-editor-popover-form-item">
            <label htmlFor="captionText" className="acms-admin-block-editor-popover-form-item-label">
              キャプション
            </label>
            <input
              type="text"
              id="captionText"
              className="acms-admin-form-width-full"
              defaultValue={caption}
              ref={captionRef}
              placeholder=""
            />
          </div>
          <div className="acms-admin-block-editor-popover-form-item">
            <label htmlFor="altText" className="acms-admin-block-editor-popover-form-item-label">
              Alt（代替テキスト）
            </label>
            <textarea
              id="altText"
              defaultValue={alt}
              ref={altRef}
              className="acms-admin-form-width-full"
              placeholder=""
            />
          </div>
          <div className="acms-admin-block-editor-popover-form-button-group">
            <PopoverClose asChild>
              <ButtonV2
                variant="filled"
                size="small"
                type="submit"
                aria-label="キャプションを適用"
                onClick={() => {
                  onChange(captionRef.current?.value ?? '', altRef.current?.value ?? '');
                }}
              >
                適用
              </ButtonV2>
            </PopoverClose>
          </div>
        </form>
      </PopoverContent>
    </Popover>
  );
});

ImageBlockCaption.displayName = 'ImageBlockCaption';
