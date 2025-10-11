import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import { WysiwygAttributes } from './types';
import { WysiwygUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<WysiwygAttributes>) => {
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
        <WysiwygUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
