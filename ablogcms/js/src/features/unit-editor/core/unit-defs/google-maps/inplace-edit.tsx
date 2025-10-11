import InplaceUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import type { GoogleMapsAttributes } from './types';
import { GoogleMapsUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<GoogleMapsAttributes>) => {
  return (
    <div>
      <div>
        <InplaceUnitToolbar editor={editor} unit={unit} features={{ align: true, anker: true }} />
      </div>
      <div>
        <GoogleMapsUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
