import { ReactNodeViewRenderer } from '@tiptap/react';
import { Range } from '@tiptap/core';
import { Selection } from '@tiptap/pm/state';
import { LinkButtonView } from './components/LinkButtonView';
import { ImageBlock } from '../ImageBlock';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    linkButton: {
      setLinkButtonBlock: () => ReturnType;
      setLinkButtonBlockAt: (attributes: { pos: number | Range }) => ReturnType;
      setLinkButtonBlockAlign: (align: 'left' | 'center' | 'right') => ReturnType;
      setLinkButtonBlockLink: (link: string, openInNewTab?: boolean) => ReturnType;
    };
  }
}

export const LinkButton = ImageBlock.extend({
  name: 'linkButton',
  group: 'block',
  content: 'inline*',
  atom: false,
  selectable: true,
  defining: true,
  isolating: true,

  addAttributes() {
    return {
      href: {
        default: '',
        renderHTML: (attrs) => ({
          'data-href': attrs.href || '',
        }),
      },
      align: {
        default: 'center',
        renderHTML: (attrs) => ({
          'data-align': attrs.align ?? 'center',
        }),
      },
      target: {
        default: null,
        renderHTML: (attrs) => ({
          'data-target': attrs.target ?? null,
        }),
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: `div[data-type="${this.name}"]`,
        getAttrs: (element: HTMLElement) => {
          const link = element.querySelector('a');
          return {
            href: link?.getAttribute('href'),
            align: element?.getAttribute('data-align') || 'center',
            target: link?.target || null,
          };
        },
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    const id = HTMLAttributes.id || null;
    const className = HTMLAttributes.class || '';
    const href = HTMLAttributes['data-href'] || '';
    const align = HTMLAttributes['data-align'] || 'center';
    const target = HTMLAttributes['data-target'] || null;

    const linkButton = [
      'a',
      {
        href,
        class: 'link-button-block-link',
        'data-type': 'button',
        ...(target
          ? {
              target,
              rel: 'noopener noreferrer',
            }
          : {}),
      },
      0,
    ];

    const cleanedClassNames = className
      .split(/\s+/)
      .filter((cls: string) => !['align-left', 'align-center', 'align-right'].includes(cls));
    const classList = new Set(['link-button-block', `align-${align}`, ...cleanedClassNames]);
    const finalClass = Array.from(classList).join(' ');

    return [
      'div',
      {
        id,
        class: finalClass,
        'data-type': this.name,
        'data-align': align,
      },
      linkButton,
    ];
  },

  addCommands() {
    return {
      setLinkButtonBlock:
        () =>
        ({ commands }) =>
          commands.insertContent({ type: 'linkButton' }),

      setLinkButtonBlockAt:
        (attrs) =>
        ({ commands }) =>
          commands.insertContentAt(attrs.pos, { type: 'linkButton' }),

      setLinkButtonBlockAlign:
        (align) =>
        ({ commands }) =>
          commands.updateAttributes('linkButton', { align }),

      setLinkButtonBlockLink:
        (link, openInNewTab = false) =>
        ({ commands }) =>
          commands.updateAttributes('linkButton', { href: link, target: openInNewTab ? '_blank' : null }),
    };
  },

  addKeyboardShortcuts() {
    return {
      Enter: ({ editor }) => {
        const { state, dispatch } = editor.view;
        const { tr, selection } = state;
        const { $from } = selection;

        for (let { depth } = $from; depth >= 0; depth--) {
          const node = $from.node(depth);
          if (node.type.name === 'linkButton') {
            const pos = $from.after(depth);

            const paragraph = editor.schema.nodes.paragraph.create();
            tr.insert(pos, paragraph);

            const resolvedPos = tr.doc.resolve(pos);
            const newSelection = Selection.near(resolvedPos);

            tr.setSelection(newSelection).scrollIntoView();
            dispatch(tr);
            return true;
          }
        }
        return false;
      },
      Backspace: ({ editor }) => {
        const { state, dispatch } = editor.view;
        const { selection } = state;

        const { $from } = selection;
        for (let { depth } = $from; depth >= 0; depth--) {
          const node = $from.node(depth);
          if (node.type.name === 'linkButton') {
            const pos = $from.before(depth);

            // ノードが空かどうかを判定
            const isEmpty = node.content.size === 0;

            // キャレットがそのノードの先頭にいるかどうかを確認
            const isAtStart = $from.pos === pos + 1;

            if (isEmpty && isAtStart) {
              // ノード全体を削除
              const tr = state.tr.delete(pos, pos + node.nodeSize);
              dispatch(tr.scrollIntoView());
              return true;
            }
          }
        }
        return false;
      },
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(LinkButtonView);
  },
});

export default LinkButton;
