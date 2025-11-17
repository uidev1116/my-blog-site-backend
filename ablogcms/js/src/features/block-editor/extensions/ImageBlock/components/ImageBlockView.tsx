import { useCallback, useRef } from 'react';
import { Editor, NodeViewWrapper } from '@tiptap/react';
import { Node } from '@tiptap/pm/model';
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
      };
    };
  };
  const imageWrapperRef = useRef<HTMLDivElement>(null);
  const { src, alt, caption } = node.attrs;

  const wrapperClassName = cn(
    node.attrs.align === 'left' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-left',
    node.attrs.align === 'right' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-right',
    node.attrs.align === 'center' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-center'
  );

  const figureClassName = cn('acms-admin-block-editor-image', node.attrs.class);

  const onClick = useCallback(() => {
    editor.commands.setNodeSelection(getPos());
  }, [getPos, editor.commands]);

  return (
    <NodeViewWrapper contentEditable={false}>
      <div className={wrapperClassName}>
        <figure
          className={figureClassName}
          contentEditable={false}
          ref={imageWrapperRef}
          style={{ maxWidth: node.attrs.displayWidth }}
        >
          {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events,jsx-a11y/no-noninteractive-element-interactions */}
          <img
            className="block"
            src={src}
            alt={alt}
            onClick={onClick}
            data-mid={node.attrs.mediaId}
            draggable={false}
          />
          {caption && <figcaption className="acms-admin-block-editor-image-caption">{caption}</figcaption>}
        </figure>
      </div>
    </NodeViewWrapper>
  );
};

export default ImageBlockView;
