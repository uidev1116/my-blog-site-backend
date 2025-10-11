import type { UnitInplaceEditProps } from '@features/unit-editor/core/types/unit';
import InplaceToolbar from '@features/unit-editor/components/unit-toolbar/presets/inplace-toolbar';
import type { MarkdownAttributes } from './types';
import { MarkdownUnitContent } from './edit';

const InplaceEdit = ({ editor, unit }: UnitInplaceEditProps<MarkdownAttributes>) => {
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
        <MarkdownUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default InplaceEdit;
