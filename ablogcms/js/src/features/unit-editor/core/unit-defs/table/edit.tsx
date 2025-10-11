import ServerSideRenderer from '@features/unit-editor/core/components/server-side-renderer';
import UnitContent from '@features/unit-editor/components/unit-content';
import CommonUnitToolbar from '@features/unit-editor/components/unit-toolbar/presets/common-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import useTab from '@features/unit-editor/hooks/use-tab';
import { useCallback } from 'react';
import useMergeRefs from '@hooks/use-merge-refs';
import useATable from './use-a-table';

// 個別のinterface定義
interface TableUnitContentProps {
  unit: UnitEditProps['unit'];
  editor: UnitEditProps['editor'];
}

// 共通化されたUnitContentコンポーネント
export const TableUnitContent = ({ unit, editor }: TableUnitContentProps) => {
  const aTable = useATable();
  const tab = useTab();

  const handleRender = useCallback(() => {
    aTable.mount();
    tab.apply();

    return () => {
      aTable.unmount();
      tab.destroy();
    };
  }, [aTable, tab]);

  return (
    <UnitContent unit={unit}>
      <ServerSideRenderer ref={useMergeRefs(aTable.ref, tab.ref)} unit={unit} editor={editor} onRender={handleRender} />
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
        <TableUnitContent unit={unit} editor={editor} />
      </div>
    </div>
  );
};

export default Edit;
