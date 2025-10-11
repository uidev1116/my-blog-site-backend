import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import { CodeAttributes } from './types';
import { CodeUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<CodeAttributes>) => {
  return (
    <div>
      <div>
        <InplaceToolbar
          editor={editor}
          unit={unit}
          features={{
            align: false,
            anker: true,
          }}
        />
      </div>

      <div>
        <CodeUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
