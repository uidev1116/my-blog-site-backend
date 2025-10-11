import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import { CodeAttributes } from './types';

// 個別のinterface定義
interface CodeUnitContentProps {
  unit: UnitEditProps<CodeAttributes>['unit'];
  editor: UnitEditProps<CodeAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const CodeUnitContent = ({ unit, editor }: CodeUnitContentProps) => {
  const handleChange = useCallback(
    (event: React.ChangeEvent<HTMLTextAreaElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        value: event.target.value,
      });
    },
    [editor, unit.id]
  );

  return (
    <UnitContent unit={unit}>
      <textarea
        name={`code_value_${unit.id}`}
        aria-label="コードユニットに内容を入力する"
        className="acms-admin-form-width-full acms-admin-form-auto-size"
        style={{ '--acms-admin-form-control-min-height': '5em' } as React.CSSProperties}
        placeholder="コードを入力してください"
        value={unit.attributes.value}
        onChange={handleChange}
      />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<CodeAttributes>) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar
          editor={editor}
          unit={unit}
          handleProps={handleProps}
          features={{
            align: false,
          }}
        />
      </div>

      <div>
        <CodeUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
