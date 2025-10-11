import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { lazy, Suspense, useCallback } from 'react';
import type { BlockEditorAttributes } from './types';

const BlockEditor = lazy(
  () => import(/* webpackChunkName: "block-editor" */ '@features/block-editor/components/BlockEditor/BlockEditor')
);

// 個別のinterface定義
interface BlockEditorUnitContentProps {
  unit: UnitEditProps<BlockEditorAttributes>['unit'];
  editor: UnitEditProps<BlockEditorAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const BlockEditorUnitContent = ({ unit, editor }: BlockEditorUnitContentProps) => {
  const handleUpdate = useCallback(
    (html: string) => {
      editor.commands.setUnitAttributes(unit.id, {
        html,
      });
    },
    [editor, unit.id]
  );
  return (
    <UnitContent unit={unit}>
      <Suspense fallback={null}>
        <BlockEditor
          {...ACMS.Config.blockEditorConfig.editorProps}
          defaultValue={unit.attributes.html}
          onUpdate={handleUpdate}
        />
      </Suspense>
      <input type="hidden" name={`block-editor_html_${unit.id}`} value={unit.attributes.html} />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<BlockEditorAttributes>) => {
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
        <BlockEditorUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
