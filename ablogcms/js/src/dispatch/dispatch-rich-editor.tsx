import { Suspense } from 'react';
import { render } from '../utils/react';
import loadRichEditorModulesAsync from '../features/rich-editor/loadModulesAsync';

export default async function dispatchRichEditor(context: Document | Element) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.SmartBlockMark);

  if (elements.length === 0) {
    return;
  }

  const editMark = ACMS.Config.PaperEditorEditMark ? ACMS.Config.PaperEditorEditMark : ACMS.Config.SmartBlockEditMark;

  const { createProps, setupExpand, RichEditor } = await loadRichEditorModulesAsync();

  elements.forEach((element) => {
    const editorEdit = element.querySelector<HTMLElement>(editMark);
    if (editorEdit === null) {
      return;
    }
    const props = createProps(element);

    render(
      <Suspense fallback={null}>
        <RichEditor {...props} />
      </Suspense>,
      editorEdit
    );
  });
  setupExpand(context);
}
