import type { EditorProps } from '../components/rich-editor/rich-editor';
import {
  Paragraph,
  Heading1,
  Heading2,
  Heading3,
  Heading4,
  Heading5,
  Heading6,
  ListItem,
  BulletList,
  OrderedList,
  Blockquote,
  Code,
  Table,
  Media,
  Emphasis,
  Underline,
  Strike,
  Link,
  MoveDown,
  MoveUp,
  Trash,
  Strong,
  Embed,
  DefaultKeys,
  DefaultPlugins,
  CustomBlock,
  CustomMark,
  Heading1Icon,
  Heading2Icon,
  Heading3Icon,
  Heading4Icon,
  Heading5Icon,
  Heading6Icon,
} from '../extensions';

export default function createProps(element: HTMLElement, options: Partial<EditorProps> = {}): EditorProps {
  // 2.11.1時点ではPaperEditorなのでその設定を吸収（PaperEditorがある場合はそちらを優先）
  const { Config } = ACMS;
  const config = {
    bodyMark: Config.PaperEditorBodyMark ? Config.PaperEditorBodyMark : Config.SmartBlockBodyMark,
    titleMark: Config.PaperEditorTitleMark ? Config.PaperEditorTitleMark : Config.SmartBlockTitleMark,
    minHeight: Config.PaperEditorUnitMinHeight ? Config.PaperEditorUnitMinHeight : Config.SmartBlockUnitMinHeight,
    maxHeight: Config.PaperEditorUnitMaxHeight ? Config.PaperEditorUnitMaxHeight : Config.SmartBlockUnitMaxHeight,
    titlePlaceholder: Config.SmartBlockTitlePlaceholder,
  };
  if (ACMS.Config.PaperEditorConf) {
    ACMS.Config.SmartBlockConf = ACMS.Config.PaperEditorConf;
  }
  if (ACMS.Config.PaperEditorReplace) {
    ACMS.Config.SmartBlockReplace = ACMS.Config.PaperEditorReplace;
  }
  if (ACMS.Config.PaperEditorRemoves) {
    ACMS.Config.SmartBlockRemoves = ACMS.Config.PaperEditorRemoves;
  }
  if (ACMS.Config.PaperEditorAdds) {
    ACMS.Config.SmartBlockAdds = ACMS.Config.PaperEditorAdds;
  }

  const Extensions = {
    Paragraph,
    Heading1,
    Heading2,
    Heading3,
    Heading4,
    Heading5,
    Heading6,
    ListItem,
    BulletList,
    OrderedList,
    Blockquote,
    Code,
    Table,
    Media,
    Emphasis,
    Underline,
    Strike,
    Link,
    MoveDown,
    MoveUp,
    Trash,
    Strong,
    Embed,
    DefaultKeys,
    DefaultPlugins,
    CustomBlock,
    CustomMark,
  };

  const icons = {
    Heading1Icon: <Heading1Icon style={{ width: '24px', height: '24px' }} />,
    Heading2Icon: <Heading2Icon style={{ width: '24px', height: '24px' }} />,
    Heading3Icon: <Heading3Icon style={{ width: '24px', height: '24px' }} />,
    Heading4Icon: <Heading4Icon style={{ width: '24px', height: '24px' }} />,
    Heading5Icon: <Heading5Icon style={{ width: '24px', height: '24px' }} />,
    Heading6Icon: <Heading6Icon style={{ width: '24px', height: '24px' }} />,
  };

  const editorBody = element.querySelector<HTMLInputElement>(config.bodyMark);
  const editorTitle = element.querySelector<HTMLInputElement>(config.titleMark);

  const html = editorBody ? editorBody.value : '';
  const title = editorTitle ? editorTitle.value : '';
  const useTitle = element.dataset.useTitle === 'true';

  const extensions = ACMS.Config.SmartBlockConf(Extensions, element, icons);
  const replacements = ACMS.Config.SmartBlockReplace(Extensions);
  const removes = ACMS.Config.SmartBlockRemoves;
  const adds = ACMS.Config.SmartBlockAdds(Extensions);

  const onChange: EditorProps['onChange'] = (body) => {
    if (editorBody) {
      editorBody.value = JSON.stringify(body);
    }
    options.onChange?.(body);
  };

  const { minHeight, maxHeight, titlePlaceholder } = config;

  return {
    html,
    title,
    useTitle,
    extensions,
    replacements,
    removes,
    adds,
    minHeight,
    maxHeight,
    titlePlaceholder,
    ...options,
    onChange,
  };
}
