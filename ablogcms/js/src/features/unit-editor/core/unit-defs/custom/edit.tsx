import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import useBuildinJs from './use-buildin-js';

// 個別のinterface定義
interface CustomUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const CustomUnitContent = ({ unit, editor }: CustomUnitContentProps) => {
  const buildinJs = useBuildinJs();

  const handleRender = useCallback(() => {
    buildinJs.apply();
  }, [buildinJs]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer ref={buildinJs.ref} unit={unit} editor={editor} onRender={handleRender} httpMethod="POST" />
    </UnitContent>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps) => {
  return (
    <div>
      <div>
        <CommonUnitToolbar editor={editor} unit={unit} handleProps={handleProps} />
      </div>

      <div>
        <CustomUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
