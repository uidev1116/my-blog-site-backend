import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import type { ModuleAttributes } from './types';
import ModuleSelect from './module-select';

// 個別のinterface定義
interface ModuleUnitContentProps {
  unit: UnitEditProps<ModuleAttributes>['unit'];
  editor: UnitEditProps<ModuleAttributes>['editor'];
}

// 共通化されたUnitContentコンポーネント
export const ModuleUnitContent = ({ unit, editor }: ModuleUnitContentProps) => {
  const handleChange = useCallback(
    (value: ModuleAttributes) => {
      editor.commands.setUnitAttributes(unit.id, value);
    },
    [editor, unit]
  );

  return (
    <UnitContent unit={unit}>
      <ModuleSelect value={unit.attributes} onChange={handleChange} />
      <input type="hidden" name={`module_mid_${unit.id}`} defaultValue={unit.attributes.mid ?? ''} />
      <input type="hidden" name={`module_tpl_${unit.id}`} defaultValue={unit.attributes.tpl} />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<ModuleAttributes>) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar
          editor={editor}
          unit={unit}
          handleProps={handleProps}
          features={{
            anker: false,
          }}
        />
      </div>

      <div>
        <ModuleUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
