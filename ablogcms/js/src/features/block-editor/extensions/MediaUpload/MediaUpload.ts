import { Node, ReactNodeViewRenderer } from '@tiptap/react';
import { Editor } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import { EditorView } from '@tiptap/pm/view';
import { Dropzone } from '@features/block-editor/extensions/MediaUpload/view/Dropzone';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    mediaSelect: {
      uploadMediaImage: (file?: File[]) => ReturnType;
      uploadMediaFile: (file?: File[]) => ReturnType;
    };
  }
}

// 画像のコピーペースト対応
const imagePastePlugin = (editor: Editor) =>
  new Plugin({
    props: {
      handlePaste(view: EditorView, event: ClipboardEvent): boolean {
        const files = event.clipboardData?.files;
        // 通常の image file の貼り付け
        if (files && files.length > 0) {
          const images = Array.from(files).filter((file) => file.type.startsWith('image/'));
          if (images.length === 0) return false;

          event.preventDefault(); // 通常の貼り付け処理を止める
          editor.commands.uploadMediaImage(images);
          return true;
        }
        return false;
      },
    },
  });

export const MediaUpload = Node.create({
  name: 'MediaUpload',

  isolating: true,

  defining: true,

  group: 'block',

  draggable: false,

  selectable: true,

  inline: false,

  addAttributes() {
    return {
      __pastedFiles: {
        default: [],
        parseHTML: () => [],
        renderHTML: () => null,
      },
      __mediaType: {
        default: 'image', // 'image' or 'file'
        parseHTML: (element) => element.getAttribute('data-media-type') || 'image',
        renderHTML: (attrs) => ({ 'data-media-type': attrs.__mediaType }),
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: `div[data-type="${this.name}"]`,
        getAttrs: () => ({}),
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', { 'data-type': this.name, ...HTMLAttributes }];
  },

  addCommands() {
    return {
      uploadMediaImage:
        (files?: File[]) =>
        ({ commands }) =>
          commands.insertContent({
            type: this.name,
            attrs: {
              __pastedFiles: files ?? [],
              __mediaType: 'image',
            },
          }),

      uploadMediaFile:
        (files?: File[]) =>
        ({ commands }) =>
          commands.insertContent({
            type: this.name,
            attrs: {
              __pastedFiles: files ?? [],
              __mediaType: 'file',
            },
          }),
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(Dropzone);
  },

  addProseMirrorPlugins() {
    return [imagePastePlugin(this.editor)];
  },
});

export default MediaUpload;
