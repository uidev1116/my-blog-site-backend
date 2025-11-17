import { useCallback, useRef } from 'react';
import { Editor, NodeViewWrapper } from '@tiptap/react';
import { Node } from '@tiptap/pm/model';
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
  const { displayType, icon, mediaId, alt, caption, iconWidth, iconHeight } = node.attrs;

  const wrapperClassName = cn(
    node.attrs.align === 'left' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-left',
    node.attrs.align === 'right' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-right',
    node.attrs.align === 'center' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-center',
    node.attrs.class
  );

  const onClick = useCallback(
    (event: React.SyntheticEvent) => {
      event.preventDefault();
      editor.commands.setNodeSelection(getPos());
    },
    [getPos, editor.commands]
  );

  return (
    <NodeViewWrapper contentEditable={false}>
      <div className={wrapperClassName}>
        <div className="acms-admin-block-editor-file" contentEditable={false} ref={fileWrapperRef}>
          {displayType === 'icon' ? (
            <>
              {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events,jsx-a11y/no-noninteractive-element-interactions */}
              <img
                className="block"
                src={icon}
                alt={alt}
                width={iconWidth}
                height={iconHeight}
                onClick={onClick}
                data-mid={mediaId}
                draggable={false}
              />
              {caption && <p className="caption">{caption}</p>}
            </>
          ) : (
            <>
              {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events,jsx-a11y/no-noninteractive-element-interactions */}
              <div
                role="button"
                tabIndex={0}
                className="acms-admin-block-editor-file-link"
                onClick={onClick}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    onClick(e);
                  }
                }}
              >
                <img
                  className="block"
                  src={icon}
                  alt={alt}
                  width={iconWidth}
                  height={iconHeight}
                  data-mid={mediaId}
                  draggable={false}
                />
                {caption && <p className="caption">{caption}</p>}
              </div>
            </>
          )}
        </div>
      </div>
    </NodeViewWrapper>
  );
};

export default FileBlockView;
