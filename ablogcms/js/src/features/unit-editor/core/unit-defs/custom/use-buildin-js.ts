import { useCallback, useRef } from 'react';
import dispatchAtableField from '../../../../../dispatch/dispatch-a-table-field';
import dispatchResizeImageCF from '../../../../../dispatch/dispatch-resize-image-cf';
import dispatchFlatpicker from '../../../../../dispatch/dispatch-flatpicker';
import dispatchLiteEditorField from '../../../../../dispatch/dispatch-lite-editor-field';
import dispatchBlockEditor from '../../../../../dispatch/dispatch-block-editor';
import dispatchSelect2 from '../../../../../dispatch/dispatch-select2';
import dispatchMediaField from '../../../../../dispatch/media/dispatch-media-field';
import dispatchRichEditor from '../../../../../dispatch/dispatch-rich-editor';

/**
 * カスタムユニットで利用できる組み込みJS
 * - fieldgroupSortable
 * - a-table-field
 * - resize-image-cf
 * - flatpicker
 * - lite-editor-field
 * - block-editor
 * - rich-editor
 * - wysiwyg
 * - select2
 * - media-field
 */
export default function useBuildinJs() {
  const ref = useRef<HTMLScriptElement>(null);

  const apply = useCallback(() => {
    if (ref.current) {
      $(ACMS.Config.fieldgroupSortableMark, ref.current).each(function () {
        ACMS.Dispatch.fieldgroupSortable(this);
      });

      dispatchAtableField(ref.current);
      dispatchResizeImageCF(ref.current);
      dispatchFlatpicker(ref.current);
      dispatchLiteEditorField(ref.current);
      dispatchBlockEditor(ref.current);
      dispatchRichEditor(ref.current);
      ACMS.Dispatch.wysiwyg.dispatch(ref.current);
      dispatchSelect2(ref.current);
      dispatchMediaField(ref.current);
    }
  }, []);

  return {
    ref,
    apply,
  };
}
