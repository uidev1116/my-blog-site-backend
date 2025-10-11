import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import useTab from '@features/unit-editor/hooks/use-tab';
import { useCallback } from 'react';

// 個別のinterface定義
interface VideoUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const VideoUnitContent = ({ unit, editor }: VideoUnitContentProps) => {
  const tab = useTab();

  const handleRender = useCallback(() => {
    tab.apply();

    return () => {
      tab.destroy();
    };
  }, [tab]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer ref={tab.ref} unit={unit} editor={editor} onRender={handleRender} />
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
        <VideoUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
