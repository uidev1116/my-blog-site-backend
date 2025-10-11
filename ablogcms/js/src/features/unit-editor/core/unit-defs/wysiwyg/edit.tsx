import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import { WysiwygEditor } from '@components/wysiwyg-editor';
import { WysiwygAttributes } from './types';

// 個別のinterface定義
interface WysiwygUnitContentProps {
  unit: UnitEditProps<WysiwygAttributes>['unit'];
  editor: UnitEditProps<WysiwygAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const WysiwygUnitContent = ({ unit, editor }: WysiwygUnitContentProps) => {
  const handleChange = useCallback(
    (value: string) => {
      editor.commands.setUnitAttributes(unit.id, { html: value });
    },
    [editor, unit.id]
  );

  return (
    <UnitContent unit={unit}>
      <WysiwygEditor
        name={`wysiwyg_html_${unit.id}`}
        aria-label="WYSIWYGユニットに内容を入力する"
        className="acms-admin-form-width-full"
        value={unit.attributes.html}
        onChange={handleChange}
        options={ACMS.Config.wysiwygConfig}
      />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<WysiwygAttributes>) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar
          editor={editor}
          unit={unit}
          handleProps={handleProps}
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

export default Edit;
