import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import { TableUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps) => {
  return (
    <div>
      <div>
        <InplaceToolbar editor={editor} unit={unit} features={{ align: false, anker: true }} />
      </div>

      <div>
        <TableUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
