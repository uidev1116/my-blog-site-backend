import { Editor, Extension } from '@tiptap/core';
import { ReactRenderer } from '@tiptap/react';
import Suggestion, { SuggestionKeyDownProps, SuggestionProps } from '@tiptap/suggestion';
import { PluginKey } from '@tiptap/pm/state';
import BlockMenu from './BlockMenu';

type BlockMenuProps = React.ComponentPropsWithoutRef<typeof BlockMenu>;
type BlockMenuRef = React.ComponentRef<typeof BlockMenu>;

const commonMenuProps: Partial<BlockMenuProps> = {
  isOpen: true,
  strategy: 'fixed',
  placement: 'bottom-start',
  focusManagerOptions: {
    initialFocus: -1,
  },
};

const extensionName = 'slashCommand';

const SlashCommand = Extension.create({
  name: extensionName,

  priority: 200,

  addOptions() {
    return {
      getItems: () => [],
    };
  },

  addProseMirrorPlugins() {
    return [
      Suggestion({
        editor: this.editor,
        char: '/',
        allowSpaces: true,
        startOfLine: true,
        pluginKey: new PluginKey(extensionName),
        allow: ({ state, range }) => {
          const $from = state.doc.resolve(range.from);
          const isRootDepth = $from.depth === 1;
          const isParagraph = $from.parent.type.name === 'paragraph';
          const isStartOfNode = $from.parent.textContent?.charAt(0) === '/';
          // TODO
          const isInColumn = this.editor.isActive('column');

          const afterContent = $from.parent.textContent?.substring($from.parent.textContent?.indexOf('/'));
          const isValidAfterContent = !afterContent?.endsWith('  ');

          return (
            ((isRootDepth && isParagraph && isStartOfNode) || (isInColumn && isParagraph && isStartOfNode)) &&
            isValidAfterContent
          );
        },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        command: ({ editor, props }: { editor: Editor; props: any }) => {
          const { view, state } = editor;
          const { $head, $from } = view.state.selection;

          const end = $from.pos;
          const from = $head?.nodeBefore
            ? end - ($head.nodeBefore.text?.substring($head.nodeBefore.text?.indexOf('/')).length ?? 0)
            : $from.start();

          const tr = state.tr.deleteRange(from, end);
          view.dispatch(tr);

          props.action(editor);
          view.focus();
        },
        items: ({ query, editor }) => {
          return this.options.getItems({ query, editor });
        },
        render: () => {
          let component: InstanceType<typeof ReactRenderer<BlockMenuRef, BlockMenuProps>> | null = null;

          let scrollHandler: (() => void) | null = null;

          return {
            onStart: (props: SuggestionProps) => {
              component = new ReactRenderer(BlockMenu, {
                props,
                editor: props.editor,
              });

              const { view } = props.editor;

              const getAnchorRect = () => {
                if (!props.clientRect) {
                  return props.editor.storage[extensionName].rect;
                }

                const rect = props.clientRect();

                if (!rect) {
                  return props.editor.storage[extensionName].rect;
                }

                if (component === null) {
                  return props.editor.storage[extensionName].rect;
                }

                const { element } = component;

                if (!(element instanceof HTMLElement)) {
                  return props.editor.storage[extensionName].rect;
                }

                let yPos = rect.y;

                if (rect.top + element.offsetHeight + 40 > window.innerHeight) {
                  const diff = rect.top + element.offsetHeight - window.innerHeight + 40;
                  yPos = rect.y - diff;
                }

                return new DOMRect(rect.x, yPos, rect.width, rect.height);
              };

              scrollHandler = () => {
                if (component === null) {
                  return;
                }

                const newProps: Partial<BlockMenuProps> = {
                  ...props,
                  ...commonMenuProps,
                  getAnchorRect,
                };

                component.updateProps(newProps);
              };

              view.dom.parentElement?.addEventListener('scroll', scrollHandler);

              component.updateProps({
                ...props,
                ...commonMenuProps,
                getAnchorRect,
              });
            },

            onUpdate(props: SuggestionProps) {
              if (component === null) {
                return;
              }

              component.updateProps(props);

              const { view } = props.editor;

              const getAnchorRect = () => {
                if (!props.clientRect) {
                  return props.editor.storage[extensionName].rect;
                }

                const rect = props.clientRect();

                if (!rect) {
                  return props.editor.storage[extensionName].rect;
                }

                // Account for when the editor is bound inside a container that doesn't go all the way to the edge of the screen
                return new DOMRect(rect.x, rect.y, rect.width, rect.height);
              };

              const scrollHandler = () => {
                if (component === null) {
                  return;
                }

                component.updateProps({
                  ...props,
                  isOpen: true,
                  getAnchorRect,
                });
              };

              view.dom.parentElement?.addEventListener('scroll', scrollHandler);

              // eslint-disable-next-line no-param-reassign
              props.editor.storage[extensionName].rect = props.clientRect
                ? getAnchorRect()
                : {
                    width: 0,
                    height: 0,
                    left: 0,
                    top: 0,
                    right: 0,
                    bottom: 0,
                  };

              const newProps: Partial<BlockMenuProps> = {
                ...props,
                ...commonMenuProps,
                getAnchorRect,
              };

              component.updateProps(newProps);
            },

            onKeyDown(props: SuggestionKeyDownProps) {
              if (props.event.key === 'Escape') {
                const newProps: Partial<BlockMenuProps> = {
                  isOpen: false,
                };
                component?.updateProps(newProps);
                return true;
              }

              return component?.ref?.onKeyDown(props) || false;
            },

            onExit(props) {
              if (scrollHandler) {
                const { view } = props.editor;
                view.dom.parentElement?.removeEventListener('scroll', scrollHandler);
              }
              if (component) {
                component.destroy();
              }
            },
          };
        },
      }),
    ];
  },

  addStorage() {
    return {
      rect: {
        width: 0,
        height: 0,
        left: 0,
        top: 0,
        right: 0,
        bottom: 0,
      },
    };
  },
});

export default SlashCommand;
