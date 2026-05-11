import { useCallback, useEffect, useRef } from 'react';
import { Editor, NodeViewWrapper } from '@tiptap/react';
import { Node } from '@tiptap/pm/model';
import { MediaItem } from '@features/media/types';
import { cn } from '../../../lib/utils';

interface ImageBlockViewProps {
  editor: Editor;
  getPos: () => number;
  node: Node;
  // updateAttributes: (attrs: Record<string, string>) => void
}

export const ImageBlockView = (props: ImageBlockViewProps) => {
  const { editor, getPos, node } = props as ImageBlockViewProps & {
    node: Node & {
      attrs: {
        src: string;
        mediaId: string;
        class?: string;
        noLightbox: 'true' | 'false';
        entryId: string;
        width: string;
        height: string;
        displayWidth: string;
        align: 'left' | 'center' | 'right';
        link: string;
        target: string;
        caption: string;
      };
    };
  };
  const imageWrapperRef = useRef<HTMLDivElement>(null);
  const { src, alt, caption, width, height, displayWidth, mediaId, align, link, target, entryId } = node.attrs;

  const wrapperClassName = cn('media-image-block', `align-${align || 'left'}`);

  const figureClassName = cn(node.attrs.class);

  const onClick = useCallback(() => {
    editor.commands.setNodeSelection(getPos());
  }, [getPos, editor.commands]);

  useEffect(() => {
    const listener = (event: Event) => {
      if (!(event instanceof CustomEvent)) {
        return;
      }
      const media = event.detail.data as MediaItem;
      if (parseInt(mediaId, 10) === media.media_id) {
        if (media.media_thumbnail) {
          // 同一IDのメディア画像をすべて更新
          editor.chain().updateAllMediaImageBlocks(media).run();
        }
      }
    };
    document.addEventListener('acms.media-updated', listener);
    return () => {
      document.removeEventListener('acms.media-updated', listener);
    };
  }, [mediaId, editor]);

  return (
    <NodeViewWrapper
      contentEditable={false}
      className={wrapperClassName}
      data-type="imageBlock"
      data-align={align || 'left'}
      data-link={link || null}
      data-width={displayWidth || '100%'}
      data-eid={entryId || (typeof ACMS !== 'undefined' ? ACMS.Config.eid : undefined)}
      data-no-lightbox={node.attrs.noLightbox === 'true' ? 'true' : 'false'}
    >
      <figure
        className={figureClassName}
        contentEditable={false}
        ref={imageWrapperRef}
        style={{ maxWidth: displayWidth }}
      >
        {/* eslint-disable-next-line no-nested-ternary */}
        {link ? (
          <a
            href={link}
            target={target || undefined}
            rel={target ? 'noopener noreferrer' : undefined}
            onClick={onClick}
          >
            <img
              src={src}
              className={`unit-id-${entryId || (typeof ACMS !== 'undefined' ? ACMS.Config.eid : '')}`}
              width={width || undefined}
              height={height || undefined}
              alt={alt || ''}
              loading="lazy"
              decoding="async"
              data-mid={mediaId || undefined}
              draggable={false}
            />
          </a>
        ) : node.attrs.noLightbox !== 'true' ? (
          <a href={src} onClick={onClick}>
            <img
              src={src}
              className={`unit-id-${entryId || (typeof ACMS !== 'undefined' ? ACMS.Config.eid : '')}`}
              width={width || undefined}
              height={height || undefined}
              alt={alt || ''}
              loading="lazy"
              decoding="async"
              data-mid={mediaId || undefined}
              draggable={false}
            />
          </a>
        ) : (
          <img
            src={src}
            className={`unit-id-${entryId || (typeof ACMS !== 'undefined' ? ACMS.Config.eid : '')}`}
            width={width || undefined}
            height={height || undefined}
            alt={alt || ''}
            loading="lazy"
            decoding="async"
            data-mid={mediaId || undefined}
            draggable={false}
            role="button" // eslint-disable-line jsx-a11y/no-noninteractive-element-to-interactive-role
            tabIndex={0}
            onClick={onClick}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                onClick();
              }
            }}
          />
        )}
        {caption && <figcaption className="caption">{caption}</figcaption>}
      </figure>
    </NodeViewWrapper>
  );
};

export default ImageBlockView;
