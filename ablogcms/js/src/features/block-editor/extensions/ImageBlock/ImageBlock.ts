import { ReactNodeViewRenderer } from '@tiptap/react';
import { Editor, Range } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import { EditorView } from '@tiptap/pm/view';
import { DOMParser as PMDOMParser } from '@tiptap/pm/model';
import { MediaItem } from '@features/media/types';
import { ImageBlockView } from './components/ImageBlockView';
import { Image } from '../Image';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    imageBlock: {
      setImageBlock: (attributes: { src: string }) => ReturnType;
      setImageBlockAt: (attributes: { src: string; pos: number | Range }) => ReturnType;
      setImageBlockAlign: (align: 'left' | 'center' | 'right') => ReturnType;
      setImageBlockWidth: (width: string) => ReturnType;
      setImageBlockLink: (link: string, openInNewTab?: boolean) => ReturnType;
      setImageBlockCaption: (caption: string) => ReturnType;
      setImageBlockAlt: (alt: string) => ReturnType;
      setMediaImageBlock: (mediaItem: MediaItem, className?: string) => ReturnType;
      updateMediaImageBlock: (mediaItem: MediaItem) => ReturnType;
      toggleImageBlockLightbox: () => ReturnType;
    };
  }
}

// 🔧 画像のsrcをチェックして相対パスに変換するPasteプラグイン
const htmlImagePastePlugin = (editor: Editor) =>
  new Plugin({
    props: {
      handlePaste(view: EditorView, event: ClipboardEvent): boolean {
        const html = event.clipboardData?.getData('text/html');
        if (html) {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const imgs = doc.querySelectorAll('img');

          const checks = Array.from(imgs).map(async (img) => {
            const originalSrc = img.getAttribute('src');
            if (!originalSrc) return;
            try {
              const root = ACMS.Config.root.replace(/\/$/, '');
              const url = new URL(originalSrc);
              const relativePath = url.pathname + url.search;
              const checkUrl = `${location.origin}${root}${relativePath}`;
              const res = await fetch(checkUrl, { method: 'HEAD' });
              if (res.ok) {
                img.setAttribute('src', `${root}${relativePath}`);
              }
            } catch (e) {
              console.warn('画像URL変換失敗:', e); // eslint-disable-line no-console
            }
          });

          Promise.all(checks).then(() => {
            const slice = PMDOMParser.fromSchema(editor.schema).parseSlice(doc.body);
            const transaction = view.state.tr.replaceSelection(slice);
            view.dispatch(transaction);
          });

          return true;
        }
        return false;
      },
    },
  });

export const ImageBlock = Image.extend({
  name: 'imageBlock',
  group: 'block',
  atom: true,
  defining: true,
  isolating: true,
  editable: false,

  addAttributes() {
    return {
      src: {
        default: '',
        renderHTML: (attributes) => ({
          src: attributes.src,
        }),
      },
      align: {
        default: 'center',
        renderHTML: (attributes) => ({
          'data-align': attributes.align,
        }),
      },
      link: {
        default: null,
        renderHTML: (attributes) => ({
          'data-link': attributes.link,
        }),
      },
      target: {
        default: null,
        renderHTML: (attributes) => ({
          'data-target': attributes.target,
        }),
      },
      caption: {
        default: undefined,
        renderHTML: (attributes) => ({
          'data-caption': attributes.caption,
        }),
      },
      alt: {
        default: undefined,
        renderHTML: (attributes) => ({
          alt: attributes.alt,
        }),
      },
      width: {
        default: null,
        renderHTML: (attributes) => ({
          width: attributes.width,
        }),
      },
      height: {
        default: null,
        renderHTML: (attributes) => ({
          height: attributes.height,
        }),
      },
      displayWidth: {
        default: '100%',
        renderHTML: (attributes) => ({
          'data-width': attributes.displayWidth,
        }),
      },
      mediaId: {
        default: null,
        renderHTML: (attributes) => ({
          'data-mid': attributes.mediaId || null,
        }),
      },
      entryId: {
        default: null,
        renderHTML: (attributes) => ({
          'data-eid': attributes.entryId || ACMS.Config.eid,
        }),
      },
      noLightbox: {
        default: 'false',
        renderHTML: (attributes) => ({
          'data-no-lightbox': attributes.noLightbox === 'true' ? 'true' : 'false',
        }),
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'figure',
        getAttrs: (element: HTMLElement) => {
          const img = element.querySelector('img');
          const figcaption = element.querySelector('figcaption');
          const parent = element.closest(`[data-type="${this.name}"]`);
          const link = parent?.querySelector('a');

          return {
            src: img?.getAttribute('src'),
            align: parent?.getAttribute('data-align') || 'center',
            link: parent?.getAttribute('data-link'),
            target: link?.target || null,
            caption: figcaption?.textContent?.trim() || '',
            alt: img?.getAttribute('alt'),
            width: img?.getAttribute('width'),
            height: img?.getAttribute('height'),
            displayWidth: parent?.getAttribute('data-width'),
            mediaId: img?.getAttribute('data-mid'),
            entryId: parent?.getAttribute('data-eid'),
            noLightbox: parent?.getAttribute('data-no-lightbox') === 'true' ? 'true' : 'false',
          };
        },
      },
      {
        tag: 'img',
        getAttrs: (element: HTMLElement) => {
          if (element.closest('[data-type="fileBlock"]')) {
            return false; // ファイルブロックのため無視
          }
          return {
            src: element.getAttribute('src'),
            align: 'center',
            link: '',
            target: null,
            caption: '',
            alt: element.getAttribute('alt'),
            width: element.getAttribute('width'),
            height: element.getAttribute('height'),
            displayWidth: '',
            mediaId: null,
            entryId: null,
            noLightbox: 'false',
          };
        },
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    const id = HTMLAttributes.id || null;
    const className = HTMLAttributes.class || '';
    const src = HTMLAttributes.src || '';
    const align = HTMLAttributes['data-align'] || 'left';
    const link = HTMLAttributes['data-link'] || null;
    const target = HTMLAttributes['data-target'] || null;
    const caption = HTMLAttributes['data-caption'] || null;
    const alt = HTMLAttributes.alt || null;
    const width = HTMLAttributes.width || null;
    const height = HTMLAttributes.height || null;
    const displayWidth = HTMLAttributes['data-width'] || '100%';
    const mediaId = HTMLAttributes['data-mid'] || null;
    const eid = HTMLAttributes['data-eid'] || ACMS.Config.eid;
    const shouldUseLightbox = HTMLAttributes['data-no-lightbox'] !== 'true';

    const img = [
      'img',
      {
        src,
        class: `unit-id-${eid}`,
        width,
        height,
        alt,
        loading: 'lazy',
        decoding: 'async',
        'data-mid': mediaId || null,
      } as unknown,
    ];

    let anchor = img;
    if (link) {
      anchor = [
        'a',
        {
          href: link,
          ...(target
            ? {
                target,
                rel: 'noopener noreferrer',
              }
            : {}),
        },
        img,
      ];
    } else if (shouldUseLightbox) {
      anchor = [
        'a',
        {
          href: src,
        },
        img,
      ];
    }
    const figureChildren = [anchor];

    if (caption) {
      figureChildren.push([
        'figcaption',
        {
          class: 'caption',
        },
        caption,
      ]);
    }

    return [
      'div',
      {
        class: `media-image-block align-${align}`,
        'data-type': this.name,
        'data-align': align,
        'data-link': link || null,
        'data-width': displayWidth,
        'data-eid': eid,
        'data-no-lightbox': shouldUseLightbox ? 'false' : 'true',
      },
      [
        'figure',
        {
          id,
          class: className,
          style: `max-width: ${displayWidth};`,
        },
        ...figureChildren,
      ],
    ];
  },

  addCommands() {
    const createImageAttrs = (mediaItem: MediaItem, className?: string) => {
      let width = null;
      let height = null;
      if (mediaItem.media_size.includes('x')) {
        const [tempX, tempY] = mediaItem.media_size.split('x');
        width = parseInt(tempX.trim(), 10);
        height = parseInt(tempY.trim(), 10);
      }
      return {
        src: mediaItem.media_root_path,
        class: className || null,
        mediaId: mediaItem.media_id,
        entryId: ACMS.Config.eid,
        width,
        height,
        link: mediaItem.media_link ?? null,
        caption: mediaItem.media_caption ?? null,
        alt: mediaItem.media_alt ?? null,
      };
    };

    return {
      setImageBlock:
        (attrs) =>
        ({ commands }) =>
          commands.insertContent({ type: 'imageBlock', attrs: { src: attrs.src } }),

      setImageBlockAt:
        (attrs) =>
        ({ commands }) =>
          commands.insertContentAt(attrs.pos, { type: 'imageBlock', attrs: { src: attrs.src } }),

      setImageBlockAlign:
        (align) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', { align }),

      setImageBlockWidth:
        (width) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', { displayWidth: width }),

      setImageBlockLink:
        (link, openInNewTab = false) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', { link, target: openInNewTab ? '_blank' : null }),

      setImageBlockCaption:
        (caption) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', { caption }),

      setImageBlockAlt:
        (alt) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', { alt }),

      setMediaImageBlock:
        (mediaItem, className) =>
        ({ commands }) =>
          commands.insertContent({
            type: 'imageBlock',
            attrs: createImageAttrs(mediaItem, className),
          }),

      updateMediaImageBlock:
        (mediaItem) =>
        ({ commands }) =>
          commands.updateAttributes('imageBlock', createImageAttrs(mediaItem)),

      toggleImageBlockLightbox:
        () =>
        ({ editor, commands }) => {
          const { noLightbox } = editor.getAttributes('imageBlock');
          return commands.updateAttributes('imageBlock', {
            noLightbox: noLightbox === 'true' ? 'false' : 'true',
          });
        },
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(ImageBlockView);
  },

  addProseMirrorPlugins() {
    return [htmlImagePastePlugin(this.editor)];
  },
});

export default ImageBlock;
