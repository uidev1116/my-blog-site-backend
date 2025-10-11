import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import type { ModuleAttributes } from './types';
import { ModuleUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<ModuleAttributes>) => {
  return (
    <div>
      <div>
        <InplaceToolbar
          editor={editor}
          unit={unit}
          features={{
            anker: false,
          }}
        />
      </div>

      <div>
        <ModuleUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
