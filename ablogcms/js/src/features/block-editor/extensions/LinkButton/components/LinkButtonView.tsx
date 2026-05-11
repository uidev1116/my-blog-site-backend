import React from 'react';
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

  // editor.css の .align-left/.align-center/.align-right が読み込まれていない環境でも、
  // display: flex によりインライン要素の折りたたみを防ぎ、フォーカスを外した際の
  // ProseMirror の DOM 同期時に <a> タグが消える不具合を防ぐ
  const justifyContentMap = {
    left: 'flex-start',
    center: 'center',
    right: 'flex-end',
  } as const;
  const wrapperStyle: React.CSSProperties = {
    display: 'flex',
    justifyContent: justifyContentMap[align || 'center'],
  };

  return (
    <NodeViewWrapper
      className={wrapperClassName}
      style={wrapperStyle}
      data-type="linkButton"
      data-align={align || 'center'}
    >
      {/* eslint-disable-next-line jsx-a11y/control-has-associated-label */}
      <a
        href={href || ''}
        className="link-button-block-link acms-admin-block-editor-link-button-link" // acms-admin-block-editor-link-button-link は 3.2.0 ~ 3.2.7 互換性のために残しておく
        data-type="button"
        target={target || undefined}
        rel={target ? 'noopener noreferrer' : undefined}
      >
        <NodeViewContent
          as="span"
          style={showPlaceholder ? { minWidth: '1px' } : null}
          className={showPlaceholder ? 'is-empty' : undefined}
        />
      </a>
    </NodeViewWrapper>
  );
};

export default LinkButtonView;
