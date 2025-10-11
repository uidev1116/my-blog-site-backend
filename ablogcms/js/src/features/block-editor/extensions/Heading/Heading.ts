import TiptapHeading from '@tiptap/extension-heading';

export const Heading = TiptapHeading.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
    };
  },
});

export default Heading;
