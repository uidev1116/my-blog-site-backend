import { Decoration, DecorationSet, EditorView } from 'smartblock/pm/view';
import { Plugin } from 'smartblock/pm/state';
import { findChildren } from 'smartblock/pm/utils';
import { MediaItem } from '@features/media/types';

export const MediaPlugin = () =>
  new Plugin({
    props: {
      // eslint-disable-next-line @typescript-eslint/ban-ts-comment
      // @ts-ignore
      decorations(state) {
        const { doc } = state;
        const medias = findChildren(
          doc,
          (node) => {
            if (node.type.name === 'media') {
              return true;
            }
            return false;
          },
          true
        );
        const decorations: Decoration[] = [];
        medias.forEach((media) => {
          if (!media.node.content || !media.node.content.size) {
            if (media.node.attrs.media_id) {
              decorations.push(
                Decoration.node(media.pos, media.pos + media.node.nodeSize, {
                  class: 'empty-node',
                })
              );
            }
          }
        });
        if (decorations.length) {
          return DecorationSet.create(doc, decorations);
        }
      },
    },
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    view(editorView: EditorView) {
      const handleMediaUpdate = (event: Event) => {
        if (!(event instanceof CustomEvent)) {
          return;
        }
        const media = event.detail.data as MediaItem;
        const { state, dispatch } = editorView;

        // エディター全体を走査して、同じmedia_idを持つすべてのmediaノードを見つける
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const positions: Array<{ pos: number; node: any }> = [];
        state.doc.descendants((node, pos) => {
          if (node.type.name === 'media' && node.attrs.media_id) {
            // media_idは文字列として保存されている可能性があるため、数値に変換して比較
            const nodeMediaId =
              typeof node.attrs.media_id === 'string' ? parseInt(node.attrs.media_id, 10) : node.attrs.media_id;
            if (nodeMediaId === media.media_id) {
              positions.push({ pos, node });
            }
          }
        });

        // 見つかったすべてのノードを更新
        if (positions.length > 0) {
          const { tr } = state;
          positions.forEach(({ pos, node }) => {
            const newAttrs = {
              ...node.attrs,
              media_id: media.media_id,
              src: media.media_edited,
            };
            tr.setNodeMarkup(pos, undefined, newAttrs);
          });
          dispatch(tr);
        }
      };

      document.addEventListener('acms.media-updated', handleMediaUpdate);

      return {
        destroy() {
          document.removeEventListener('acms.media-updated', handleMediaUpdate);
        },
      };
    },
    filterTransaction: () => true,
  });
