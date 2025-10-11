import { useCallback, useMemo } from 'react';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import type { RichEditorAttributes } from './types';
import AsyncRichEditor, { type AsyncRichEditorProps } from './components/async-rich-editor';

// 個別のinterface定義
interface RichEditorUnitContentProps {
  unit: UnitEditProps<RichEditorAttributes>['unit'];
  editor: UnitEditProps<RichEditorAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const RichEditorUnitContent = ({ unit, editor }: RichEditorUnitContentProps) => {
  const handleChange = useCallback<NonNullable<AsyncRichEditorProps['onChange']>>(
    (value) => {
      // JSON形式で保存するため、HTMLをJSONに変換
      const json = JSON.stringify(value);
      editor.commands.setUnitAttributes(unit.id, {
        json,
      });
    },
    [editor, unit.id]
  );

  // JSONをパースしてHTMLを取得（useMemoで最適化）
  const html = useMemo(() => {
    try {
      const parsed = JSON.parse(unit.attributes.json);
      return parsed.html || '';
    } catch {
      return '';
    }
  }, [unit.attributes.json]);

  return (
    <UnitContent unit={unit}>
      <AsyncRichEditor html={html} onChange={handleChange} />
      <input type="hidden" name={`rich-editor_json_${unit.id}`} value={unit.attributes.json} />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<RichEditorAttributes>) => {
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
        <RichEditorUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
