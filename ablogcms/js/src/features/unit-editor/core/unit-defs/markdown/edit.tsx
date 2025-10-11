import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import type { MarkdownAttributes } from './types';

// 個別のinterface定義
interface MarkdownUnitContentProps {
  unit: UnitEditProps<MarkdownAttributes>['unit'];
  editor: UnitEditProps<MarkdownAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const MarkdownUnitContent = ({ unit, editor }: MarkdownUnitContentProps) => {
  const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
    editor.commands.setUnitAttributes(unit.id, { value: event.target.value });
  };

  return (
    <UnitContent unit={unit}>
      <textarea
        name={`markdown_value_${unit.id}`}
        className="acms-admin-form-width-full acms-admin-form-auto-size"
        style={{ '--acms-admin-form-control-min-height': '5em' } as React.CSSProperties}
        value={unit.attributes.value}
        onChange={handleChange}
        placeholder="マークダウンを入力してください"
        aria-label="マークダウンユニットに内容を入力する"
      />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<MarkdownAttributes>) => {
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
        <MarkdownUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
