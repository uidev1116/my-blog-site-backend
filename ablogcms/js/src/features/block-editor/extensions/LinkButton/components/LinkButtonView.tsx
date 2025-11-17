import { useRef } from 'react';
import { NodeViewWrapper, NodeViewContent } from '@tiptap/react';
import { Node } from '@tiptap/pm/model';
import { cn } from '../../../lib/utils';

interface LinkButtonBlockViewProps {
  node: Node;
}

export const LinkButtonView = (props: LinkButtonBlockViewProps) => {
  const { node } = props as LinkButtonBlockViewProps & {
    node: Node & {
      attrs: {
        href: string;
        text: string;
        class?: string;
        align: 'left' | 'center' | 'right';
        target: '_blank' | null;
      };
    };
  };
  const fileWrapperRef = useRef<HTMLDivElement>(null);
  const { align, target } = node.attrs;

  const wrapperClassName = cn(
    align === 'left' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-left',
    align === 'right' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-right',
    align === 'center' && 'acms-admin-block-editor-wrapper acms-admin-block-editor-wrapper-center',
    node.attrs.class
  );

  const showPlaceholder = node.content.size === 0;

  return (
    <NodeViewWrapper>
      <div className={wrapperClassName}>
        <div className="acms-admin-block-editor-link-button" ref={fileWrapperRef}>
          <div className="acms-admin-block-editor-link-button-link" data-target={target}>
            {showPlaceholder && <span className="is-empty" />}
            <NodeViewContent as="span" style={showPlaceholder ? { minWidth: '1px' } : null} />
          </div>
        </div>
      </div>
    </NodeViewWrapper>
  );
};

export default LinkButtonView;
