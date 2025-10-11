import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import useTab from '@features/unit-editor/hooks/use-tab';
import { useCallback } from 'react';
import useMergeRefs from '@hooks/use-merge-refs';
import useMediaUnit from './use-media-unit';

// 個別のinterface定義
interface MediaUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const MediaUnitContent = ({ unit, editor }: MediaUnitContentProps) => {
  const mediaUnit = useMediaUnit();
  const tab = useTab();

  const handleRender = useCallback(() => {
    mediaUnit.mount();
    tab.apply();

    return () => {
      setTimeout(() => {
        mediaUnit.unmount();
      }, 0);
      tab.destroy();
    };
  }, [mediaUnit, tab]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer
        ref={useMergeRefs(mediaUnit.ref, tab.ref)}
        unit={unit}
        editor={editor}
        onRender={handleRender}
      />
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
        <MediaUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
