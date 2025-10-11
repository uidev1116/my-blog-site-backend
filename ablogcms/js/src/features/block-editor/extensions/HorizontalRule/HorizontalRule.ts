import { mergeAttributes } from '@tiptap/core';
import TiptapHorizontalRule from '@tiptap/extension-horizontal-rule';

export const HorizontalRule = TiptapHorizontalRule.extend({
  renderHTML({ HTMLAttributes }) {
    const attrs = mergeAttributes(this.options.HTMLAttributes, HTMLAttributes);
    return ['div', { 'data-type': this.name }, ['hr', attrs]];
  },
});

export default HorizontalRule;
