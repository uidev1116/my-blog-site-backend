import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import type { HtmlAttributes } from './types';

// 個別のinterface定義
interface HtmlUnitContentProps {
  unit: UnitEditProps<HtmlAttributes>['unit'];
  editor: UnitEditProps<HtmlAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const HtmlUnitContent = ({ unit, editor }: HtmlUnitContentProps) => {
  const handleChange = useCallback(
    (event: React.ChangeEvent<HTMLTextAreaElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        html: event.target.value,
      });
    },
    [editor, unit.id]
  );

  return (
    <UnitContent unit={unit}>
      <textarea
        name={`html_html_${unit.id}`}
        aria-label="自由入力ユニットに内容を入力する"
        className="acms-admin-form-width-full acms-admin-form-auto-size"
        style={{ '--acms-admin-form-control-min-height': '5em' } as React.CSSProperties}
        placeholder="HTMLを入力してください"
        value={unit.attributes.html}
        onChange={handleChange}
      />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<HtmlAttributes>) => {
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
        <HtmlUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
