import { Mark } from '@tiptap/core';

export const CustomMark = Mark.create({
  name: 'customMark', // マークの名前

  // 属性の定義
  addAttributes() {
    return {
      class: {
        default: null, // デフォルトはnull
        parseHTML: (element) => element.getAttribute('class'), // HTMLからクラスを取得
        renderHTML: (attributes) => {
          if (!attributes.class) {
            return {};
          }
          return {
            class: attributes.class, // HTMLにクラスを追加
          };
        },
      },
    };
  },

  // HTMLのパース処理
  parseHTML() {
    return [
      {
        tag: 'span.custom-mark', // マークのHTMLタグを定義
      },
    ];
  },

  // HTMLのレンダリング処理
  renderHTML({ HTMLAttributes }) {
    return ['span', HTMLAttributes, 0]; // インライン要素として出力
  },
});

export default CustomMark;
