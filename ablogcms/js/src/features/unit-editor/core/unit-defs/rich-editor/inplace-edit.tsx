import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import { RichEditorUnitContent } from './edit';
import type { RichEditorAttributes } from './types';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<RichEditorAttributes>) => {
  return (
    <div>
      <div>
        <InplaceToolbar
          editor={editor}
          unit={unit}
          features={{
            anker: false,
            align: false,
          }}
        />
      </div>

      <div>
        <RichEditorUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
