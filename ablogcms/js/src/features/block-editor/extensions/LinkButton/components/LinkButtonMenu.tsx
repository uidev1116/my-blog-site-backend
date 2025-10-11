import { BubbleMenu as BaseBubbleMenu } from '@tiptap/react';
import { useCallback, useRef } from 'react';
import { v4 as uuid } from 'uuid';

import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { MenuProps } from '@features/block-editor/components/menus/types';
import { AlignPicker } from '@features/block-editor/components/panels';
import { ImageBlockLink } from '@features/block-editor/extensions/ImageBlock/components/ImageBlockLink';
import { Icon } from '@features/block-editor/components/ui/Icon';

type TippyInstance = Parameters<
  NonNullable<NonNullable<React.ComponentPropsWithoutRef<typeof BaseBubbleMenu>['tippyOptions']>['onCreate']>
>[0];

export const LinkButtonMenu = ({ editor, appendTo }: MenuProps): JSX.Element => {
  const menuRef = useRef<HTMLDivElement>(null);
  const tippyInstance = useRef<TippyInstance | null>(null);
  const linkButtonBlock = useCallback(() => {
    const { selection } = editor.state;
    const { $from } = selection;
    // 深い階層から親を遡って linkButton を探す
    for (let { depth } = $from; depth >= 0; depth--) {
      const node = $from.node(depth);
      if (node.type.name === 'linkButton') {
        return {
          node,
          pos: $from.before(depth), // ノードの先頭位置も一緒に返すと便利
        };
      }
    }
    return null;
  }, [editor]);

  const shouldShow = useCallback(() => {
    const isActive = editor.isActive('linkButton');
    return isActive;
  }, [editor]);

  return (
    <BaseBubbleMenu
      editor={editor}
      pluginKey={`linkButtonMenu-${uuid()}`}
      shouldShow={shouldShow}
      updateDelay={0}
      tippyOptions={{
        offset: [0, 18],
        onCreate: (instance) => {
          tippyInstance.current = instance;
        },
        appendTo: () => appendTo?.current ?? document.body,
        getReferenceClientRect: () => {
          const result = linkButtonBlock();
          if (!result) return new DOMRect();

          const { pos } = result;
          const dom = editor.view.nodeDOM(pos) as HTMLElement;
          if (!dom || !dom.getBoundingClientRect) return new DOMRect();

          return dom.getBoundingClientRect();
        },
      }}
    >
      <Toolbar shouldShowContent={shouldShow()} ref={menuRef}>
        <ImageBlockLink
          initialUrl={editor.getAttributes('linkButton').href}
          initialOpenInNewTab={editor.getAttributes('linkButton').target === '_blank'}
          onChange={(link, openInNewTab) => {
            editor.chain().focus(undefined, { scrollIntoView: false }).setLinkButtonBlockLink(link, openInNewTab).run();
          }}
        />
        <Toolbar.Button
          tooltip="リンクを確認"
          onClick={() => {
            window.open(editor.getAttributes('linkButton').href, '_blank', 'noopener,noreferrer');
          }}
          aria-label="リンクを確認"
          disabled={!editor.getAttributes('linkButton').href}
        >
          <Icon name="visibility" />
        </Toolbar.Button>
        <AlignPicker
          align={editor.getAttributes('linkButton').align}
          onChange={(align) => {
            editor.chain().focus(undefined, { scrollIntoView: false }).setLinkButtonBlockAlign(align).run();
          }}
        />
      </Toolbar>
    </BaseBubbleMenu>
  );
};

export default LinkButtonMenu;
