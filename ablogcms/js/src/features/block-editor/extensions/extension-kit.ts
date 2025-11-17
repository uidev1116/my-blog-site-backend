import History from '@tiptap/extension-history';
import {
  Document,
  Dropcursor,
  Figcaption,
  Focus,
  FontStyle,
  Heading,
  Highlight,
  HorizontalRule,
  Link,
  Placeholder,
  Selection,
  StarterKit,
  Subscript,
  Superscript,
  Table,
  TableCell,
  TableHeader,
  TableRow,
  TextAlign,
  TextStyle,
  TrailingNode,
  Typography,
  Underline,
  Columns,
  Column,
  ImageBlock,
  FileBlock,
  LinkButton,
  MediaUpload,
  CustomMark,
  CustomConvertBlock,
} from '.';

export const ExtensionKit = () => {
  return [
    StarterKit.configure({
      document: false,
      dropcursor: false,
      heading: false,
      horizontalRule: false,
      history: false,
    }),
    History,
    Document,
    Columns,
    Column,
    Selection,
    Heading.configure({
      levels: [1, 2, 3, 4, 5, 6],
    }),
    HorizontalRule,
    TextStyle,
    FontStyle,
    TrailingNode,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        // Change rel to different value
        // Allow search engines to follow links(remove nofollow)
        rel: 'noopener noreferrer',
        // Remove target entirely so links open in current tab
        target: null,
      },
    }),
    Highlight.configure({ multicolor: true }),
    Underline,
    MediaUpload,
    CustomMark,
    CustomConvertBlock,
    ImageBlock,
    FileBlock,
    LinkButton,
    TextAlign.extend({
      addKeyboardShortcuts() {
        return {};
      },
    }).configure({
      types: ['heading', 'paragraph'],
    }),
    Subscript,
    Superscript,
    Table,
    TableCell,
    TableHeader,
    TableRow,
    Typography,
    Placeholder.configure({
      includeChildren: true,
      showOnlyCurrent: false,
      placeholder: () => '',
    }),
    Focus,
    Figcaption,
    Dropcursor.configure({
      width: 4,
      color: '#a3c0ef',
      class: 'acms-admin-dropcursor',
    }),
  ];
};

export default ExtensionKit;
