import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import useTab from '@features/unit-editor/hooks/use-tab';
import { useCallback } from 'react';
import useMergeRefs from '@hooks/use-merge-refs';
import useResizeImage from './use-resize-image';

// 個別のinterface定義
interface ImageUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const ImageUnitContent = ({ unit, editor }: ImageUnitContentProps) => {
  const resizeImage = useResizeImage();
  const tab = useTab();

  const handleRender = useCallback(() => {
    resizeImage.mount();
    tab.apply();

    return () => {
      resizeImage.unmount();
      tab.destroy();
    };
  }, [resizeImage, tab]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer
        ref={useMergeRefs(resizeImage.ref, tab.ref)}
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
        <ImageUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
