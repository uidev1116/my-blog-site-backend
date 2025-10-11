import { memo, useState, useEffect } from 'react';
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
  const [captionText, setCaptionText] = useState<string>(caption || '');
  const [altText, setAltText] = useState<string>(alt || '');

  useEffect(() => {
    setCaptionText(caption);
  }, [caption]);

  useEffect(() => {
    setAltText(alt);
  }, [alt]);

  return (
    <Popover modal>
      <PopoverTrigger asChild>
        <Toolbar.Button
          tooltip="キャプションを編集"
          type="button"
          active={!!captionText || !!altText}
          aria-label="キャプションを編集"
        >
          <Icon name="closed_caption" />
        </Toolbar.Button>
      </PopoverTrigger>
      <PopoverContent data-elevation="3">
        <form
          className="acms-admin-form acms-admin-block-editor-popover-form"
          onSubmit={() => onChange(captionText, altText)}
        >
          <div className="acms-admin-block-editor-popover-form-item">
            <label htmlFor="captionText" className="acms-admin-block-editor-popover-form-item-label">
              キャプション
            </label>
            <input
              type="text"
              id="captionText"
              className="acms-admin-form-width-full"
              value={captionText}
              onChange={(e) => setCaptionText(e.target.value)}
              placeholder=""
            />
          </div>
          <div className="acms-admin-block-editor-popover-form-item">
            <label htmlFor="altText" className="acms-admin-block-editor-popover-form-item-label">
              Alt（代替テキスト）
            </label>
            <textarea
              id="altText"
              value={altText}
              className="acms-admin-form-width-full"
              onChange={(e) => setAltText(e.target.value)}
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
                  onChange(captionText, altText);
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
