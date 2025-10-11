import InplaceUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import type { OpenStreetMapAttributes } from './types';
import { OpenStreetMapUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<OpenStreetMapAttributes>) => {
  return (
    <div>
      <div>
        <InplaceUnitToolbar editor={editor} unit={unit} features={{ align: true, anker: true }} />
      </div>
      <div>
        <OpenStreetMapUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
