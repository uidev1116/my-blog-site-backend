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
  const { href, align, target } = node.attrs;

  const cleanedClassNames = String(node.attrs.class ?? '')
    .split(/\s+/)
    .filter((cls: string) => !['align-left', 'align-center', 'align-right'].includes(cls))
    .join(' ')
    .trim();
  const wrapperClassName = cn(
    'link-button-block',
    'acms-admin-block-editor-link-button', // 3.2.0 ~ 3.2.7 互換性のために残しておく
    `align-${align || 'center'}`,
    cleanedClassNames
  );

  const showPlaceholder = node.content.size === 0;

  return (
    <NodeViewWrapper className={wrapperClassName} data-type="linkButton" data-align={align || 'center'}>
      <a
        href={href || ''}
        className="link-button-block-link acms-admin-block-editor-link-button-link" // acms-admin-block-editor-link-button-link は 3.2.0 ~ 3.2.7 互換性のために残しておく
        data-type="button"
        target={target || undefined}
        rel={target ? 'noopener noreferrer' : undefined}
      >
        {showPlaceholder && <span className="is-empty" />}
        <NodeViewContent as="span" style={showPlaceholder ? { minWidth: '1px' } : null} />
      </a>
    </NodeViewWrapper>
  );
};

export default LinkButtonView;
