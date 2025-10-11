import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';

import useTab from '@features/unit-editor/hooks/use-tab';
import useMergeRefs from '@hooks/use-merge-refs';
import useLiteEditor from './use-lite-editor';

// 個別のinterface定義
interface TextUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const TextUnitContent = ({ unit, editor }: TextUnitContentProps) => {
  const tab = useTab();
  const liteEditor = useLiteEditor();

  const handleRender = useCallback(() => {
    liteEditor.mount();
    tab.apply();

    return () => {
      liteEditor.unmount();
      tab.destroy();
    };
  }, [liteEditor, tab]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer
        ref={useMergeRefs(liteEditor.ref, tab.ref)}
        unit={unit}
        editor={editor}
        onRender={handleRender}
        httpMethod="POST"
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
        <TextUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
