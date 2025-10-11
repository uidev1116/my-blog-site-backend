import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import type { BlockEditorAttributes } from './types';
import { BlockEditorUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<BlockEditorAttributes>) => {
  return (
    <div>
      <div>
        <InplaceToolbar
          editor={editor}
          unit={unit}
          features={{
            align: false,
            anker: false,
          }}
        />
      </div>

      <div>
        <BlockEditorUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
