import { Extension } from '@tiptap/core';
import '@tiptap/extension-text-style';

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    fontSize: {
      setFontSize: (size: string) => ReturnType;
      unsetFontSize: () => ReturnType;
      setFontFamily: (family: string) => ReturnType;
      unsetFontFamily: () => ReturnType;
      setFontColor: (color: string) => ReturnType;
      unsetFontColor: () => ReturnType;
      setFontBgColor: (color: string) => ReturnType;
      unsetFontBgColor: () => ReturnType;
    };
  }
}

export const FontStyle = Extension.create({
  name: 'fontStyle',

  addOptions() {
    return {
      types: ['textStyle'],
    };
  },

  addGlobalAttributes() {
    return [
      {
        types: ['paragraph'],
        attributes: {
          class: {},
        },
      },
      {
        types: this.options.types,
        attributes: {
          fontSize: {
            parseHTML: (element) => {
              return element.getAttribute('data-font-size') || null;
            },
            renderHTML: (attributes) => {
              if (!attributes.fontSize) {
                return {};
              }
              return {
                style: `font-size: ${attributes.fontSize}`, // 表示用
                'data-font-size': attributes.fontSize, // 復元用
              };
            },
          },
          fontFamily: {
            parseHTML: (element) => element.getAttribute('data-font-family') || null,
            renderHTML: (attributes) => {
              if (!attributes.fontFamily) return {};
              return {
                style: `font-family: ${attributes.fontFamily.replace(/"/g, "'")}`,
                'data-font-family': attributes.fontFamily.replace(/"/g, "'"),
              };
            },
          },
          fontColor: {
            parseHTML: (element) => element.getAttribute('data-font-color') || null,
            renderHTML: (attributes) => {
              if (!attributes.fontColor) return {};
              return {
                style: `color: ${attributes.fontColor}`,
                'data-font-color': attributes.fontColor,
              };
            },
          },
        },
      },
    ];
  },

  addCommands() {
    return {
      setFontSize:
        (fontSize: string) =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontSize }).run(),
      unsetFontSize:
        () =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
      setFontFamily:
        (family: string) =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontFamily: family }).run(),

      unsetFontFamily:
        () =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontFamily: null }).removeEmptyTextStyle().run(),

      setFontColor:
        (color: string) =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontColor: color }).run(),

      unsetFontColor:
        () =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontColor: null }).removeEmptyTextStyle().run(),
    };
  },
});

export default FontStyle;
