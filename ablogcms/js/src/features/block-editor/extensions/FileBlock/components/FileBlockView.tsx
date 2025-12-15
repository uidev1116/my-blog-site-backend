import { useCallback, useEffect, useRef } from 'react';
import { Editor, NodeViewWrapper } from '@tiptap/react';
import { Node } from '@tiptap/pm/model';
import { MediaItem } from '@features/media/types';
import { cn } from '../../../lib/utils';

interface FileBlockViewProps {
  editor: Editor;
  getPos: () => number;
  node: Node;
}

export const FileBlockView = (props: FileBlockViewProps) => {
  const { editor, getPos, node } = props as FileBlockViewProps & {
    node: Node & {
      attrs: {
        displayType: 'icon' | 'button';
        href: string;
        icon: string;
        mediaId: string;
        alt?: string;
        caption?: string;
        class?: string;
      };
    };
  };
  const fileWrapperRef = useRef<HTMLDivElement>(null);
  const {
    displayType,
    href,
    target,
    icon,
    iconWidth,
    iconHeight,
    extension,
    fileSize,
    mediaId,
    alt,
    caption,
    align,
    id,
  } = node.attrs as FileBlockViewProps['node']['attrs'];

  const cleanedClassNames = String(node.attrs.class ?? '')
    .split(/\s+/)
    .filter((cls: string) => !['align-left', 'align-center', 'align-right'].includes(cls))
    .join(' ')
    .trim();

  const wrapperClassName = cn('media-file-block', `align-${align || 'left'}`, cleanedClassNames);

  const onClick = useCallback(
    (event: React.SyntheticEvent) => {
      event.preventDefault();
      editor.commands.setNodeSelection(getPos());
    },
    [getPos, editor.commands]
  );

  useEffect(() => {
    const listener = (event: Event) => {
      if (!(event instanceof CustomEvent)) {
        return;
      }
      const media = event.detail.data as MediaItem;
      if (parseInt(mediaId, 10) === media.media_id) {
        // 同一IDのメディアファイルをすべて更新
        editor.chain().updateAllMediaFileBlocks(media).run();
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
      data-type="fileBlock"
      id={id}
      className={wrapperClassName}
      data-display-type={displayType || 'icon'}
      data-icon={icon || ''}
      data-icon-width={iconWidth || '100'}
      data-icon-height={iconHeight || '100'}
      data-alt={alt ?? undefined}
      data-caption={caption ?? undefined}
      data-align={align || 'left'}
      data-mid={mediaId ?? undefined}
      data-extension={extension ?? undefined}
      data-file-size={fileSize ?? undefined}
    >
      <div className="acms-admin-block-editor-file" contentEditable={false} ref={fileWrapperRef}>
        {displayType === 'button' ? (
          <a
            href={href}
            target={target || undefined}
            rel={target ? 'noopener noreferrer' : undefined}
            onClick={onClick}
          >
            <img
              src={icon}
              width={iconWidth}
              height={iconHeight}
              alt={alt || ''}
              loading="lazy"
              decoding="async"
              draggable={false}
            />
            <p className="caption">{caption || ''}</p>
          </a>
        ) : (
          <>
            <a
              href={href}
              target={target || undefined}
              rel={target ? 'noopener noreferrer' : undefined}
              onClick={onClick}
            >
              <img
                src={icon}
                width={iconWidth}
                height={iconHeight}
                alt={alt || ''}
                loading="lazy"
                decoding="async"
                draggable={false}
              />
            </a>
            {caption && <p className="caption">{caption}</p>}
          </>
        )}
      </div>
    </NodeViewWrapper>
  );
};

export default FileBlockView;
