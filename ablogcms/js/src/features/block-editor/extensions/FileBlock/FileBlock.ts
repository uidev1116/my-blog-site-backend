import { ReactNodeViewRenderer } from '@tiptap/react';
import { Range } from '@tiptap/core';
import { MediaItem } from '@features/media/types';
import { FileBlockView } from './components/FileBlockView';
import { ImageBlock } from '../ImageBlock';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    fileBlock: {
      setFileBlock: (attributes: { src: string }) => ReturnType;
      setFileBlockAt: (attributes: { src: string; pos: number | Range }) => ReturnType;
      setFileBlockDisplayType: (displayType: 'icon' | 'button') => ReturnType;
      setFileBlockAlign: (align: 'left' | 'center' | 'right') => ReturnType;
      setFileBlockCaption: (caption: string) => ReturnType;
      setFileBlockAlt: (alt: string) => ReturnType;
      setMediaFileBlock: (mediaItem: MediaItem, className?: string) => ReturnType;
      updateMediaFileBlock: (mediaItem: MediaItem) => ReturnType;
      toggleFileBlockTarget: () => ReturnType;
    };
  }
}

export const FileBlock = ImageBlock.extend({
  name: 'fileBlock',
  group: 'block',
  atom: true,
  defining: true,
  isolating: true,
  editable: false,

  addAttributes() {
    return {
      displayType: {
        default: 'icon',
        renderHTML: (attrs) => ({
          'data-display-type': attrs.displayType || 'icon',
        }),
      },
      href: {
        default: '',
        renderHTML: (attrs) => ({
          'data-href': attrs.href || '',
        }),
      },
      icon: {
        default: '',
        renderHTML: (attrs) => ({
          'data-icon': attrs.icon || '',
        }),
      },
      iconWidth: {
        default: '',
        renderHTML: (attrs) => ({
          'data-icon-width': attrs.iconWidth || '',
        }),
      },
      iconHeight: {
        default: '',
        renderHTML: (attrs) => ({
          'data-icon-height': attrs.iconHeight || '',
        }),
      },
      extension: {
        default: null,
        renderHTML: (attrs) => ({
          'data-extension': attrs.extension ?? null,
        }),
      },
      fileSize: {
        default: null,
        renderHTML: (attrs) => ({
          'data-file-size': attrs.fileSize ?? null,
        }),
      },
      align: {
        default: 'left',
        renderHTML: (attrs) => ({
          'data-align': attrs.align ?? 'left',
        }),
      },
      target: {
        default: null,
        renderHTML: (attrs) => ({
          'data-target': attrs.target ?? null,
        }),
      },
      caption: {
        default: null,
        renderHTML: (attrs) => ({
          'data-caption': attrs.caption ?? '',
        }),
      },
      alt: {
        default: null,
        renderHTML: (attrs) => ({
          'data-alt': attrs.alt ?? '',
        }),
      },
      mediaId: {
        default: null,
        renderHTML: (attrs) => ({
          'data-mid': attrs.mediaId ?? null,
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
            displayType: element?.getAttribute('data-display-type') || 'icon',
            href: link?.getAttribute('href'),
            icon: element?.getAttribute('data-icon') || '',
            iconWidth: element?.getAttribute('data-icon-width') || '100',
            iconHeight: element?.getAttribute('data-icon-height') || '100',
            extension: element?.getAttribute('data-extension') ?? null,
            fileSize: element?.getAttribute('data-file-size') ?? null,
            align: element?.getAttribute('data-align') || 'center',
            target: link?.target || null,
            caption: element?.getAttribute('data-caption') || null,
            alt: element?.getAttribute('data-alt'),
            mediaId: element?.getAttribute('data-mid'),
          };
        },
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    const id = HTMLAttributes.id || null;
    const className = HTMLAttributes.class || '';
    const displayType = HTMLAttributes['data-display-type'] || 'icon';
    const href = HTMLAttributes['data-href'] || '';
    const icon = HTMLAttributes['data-icon'] || '';
    const iconWidth = HTMLAttributes['data-icon-width'] || '100';
    const iconHeight = HTMLAttributes['data-icon-height'] || '100';
    const extension = HTMLAttributes['data-extension'] || null;
    const fileSize = HTMLAttributes['data-file-size'] || null;
    const align = HTMLAttributes['data-align'] || 'left';
    const target = HTMLAttributes['data-target'] || null;
    const caption = HTMLAttributes['data-caption'] || null;
    const alt = HTMLAttributes['data-alt'] || null;
    const mediaId = HTMLAttributes['data-mid'] || null;

    const iconLink = [
      [
        'a',
        {
          href,
          ...(target
            ? {
                target,
                rel: 'noopener noreferrer',
              }
            : {}),
        },
        [
          'img',
          {
            src: icon,
            width: iconWidth,
            height: iconHeight,
            alt: alt || '',
            loading: 'lazy',
            decoding: 'async',
          } as unknown,
        ],
      ],
    ];
    if (caption) {
      iconLink.push(['p', { class: 'caption' }, caption]);
    }

    const buttonLink = [
      [
        'a',
        {
          href,
          ...(target
            ? {
                target,
                rel: 'noopener noreferrer',
              }
            : {}),
        },
        [
          'img',
          {
            src: icon,
            width: iconWidth,
            height: iconHeight,
            alt: alt || '',
            loading: 'lazy',
            decoding: 'async',
          } as unknown,
        ],
        ['p', { class: 'caption' }, caption || ''],
      ],
    ];

    const cleanedClassNames = className
      .split(/\s+/)
      .filter((cls: string) => !['align-left', 'align-center', 'align-right'].includes(cls));
    const classList = new Set(['media-file-block', `align-${align}`, ...cleanedClassNames]);
    const finalClass = Array.from(classList).join(' ');
    const html = displayType === 'button' ? buttonLink : iconLink;

    return [
      'div',
      {
        id,
        class: finalClass,
        'data-type': this.name,
        'data-display-type': displayType,
        'data-icon': icon,
        'data-icon-width': iconWidth,
        'data-icon-height': iconHeight,
        'data-alt': alt,
        'data-caption': caption,
        'data-align': align,
        'data-mid': mediaId,
        'data-extension': extension,
        'data-file-size': fileSize,
      },
      ['div', {}, ...html],
    ];
  },

  addCommands() {
    const createFileAttrs = (mediaItem: MediaItem, className?: string) => {
      return {
        href: mediaItem.media_permalink,
        icon: mediaItem.media_icon,
        iconWidth: mediaItem.media_icon_width || '100',
        iconHeight: mediaItem.media_icon_height || '100',
        class: className || null,
        mediaId: mediaItem.media_id,
        caption: mediaItem.media_caption || mediaItem.media_title || null,
        alt: mediaItem.media_alt || null,
        extension: mediaItem.media_ext || null,
        fileSize: mediaItem.media_filesize || null,
      };
    };

    return {
      setFileBlock:
        (attrs) =>
        ({ commands }) =>
          commands.insertContent({ type: 'fileBlock', attrs: { src: attrs.src } }),

      setFileBlockAt:
        (attrs) =>
        ({ commands }) =>
          commands.insertContentAt(attrs.pos, { type: 'fileBlock', attrs: { src: attrs.src } }),

      setFileBlockDisplayType:
        (displayType) =>
        ({ commands }) =>
          commands.updateAttributes('fileBlock', { displayType }),

      setFileBlockAlign:
        (align) =>
        ({ commands }) =>
          commands.updateAttributes('fileBlock', { align }),

      setFileBlockCaption:
        (caption) =>
        ({ commands }) =>
          commands.updateAttributes('fileBlock', { caption }),

      setFileBlockAlt:
        (alt) =>
        ({ commands }) =>
          commands.updateAttributes('fileBlock', { alt }),

      setMediaFileBlock:
        (mediaItem, className) =>
        ({ commands }) =>
          commands.insertContent({
            type: 'fileBlock',
            attrs: createFileAttrs(mediaItem, className),
          }),

      updateMediaFileBlock:
        (mediaItem) =>
        ({ commands }) =>
          commands.updateAttributes('fileBlock', createFileAttrs(mediaItem)),

      toggleFileBlockTarget:
        () =>
        ({ commands }) => {
          const { target } = this.editor.getAttributes('fileBlock');
          const newTarget = target === '_blank' ? null : '_blank';
          return commands.updateAttributes('fileBlock', { target: newTarget });
        },
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(FileBlockView);
  },
});

export default FileBlock;
