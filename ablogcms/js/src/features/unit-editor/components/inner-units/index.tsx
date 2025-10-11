import type { Editor, UnitMenuItem } from '@features/unit-editor/core';
import { UnitLastPosition, UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import { useDroppable } from '@dnd-kit/core';
import UnitInserter from '../unit-inserter';
import UnitList from '../unit-list';

interface EmptyDroppableProps extends React.HTMLAttributes<HTMLDivElement> {
  parentId: UnitTreeNode['id'];
  children: React.ReactNode;
}

const EmptyDroppable = ({ parentId, children }: EmptyDroppableProps) => {
  const { setNodeRef } = useDroppable({
    id: `droppable-${parentId}`,
    data: {
      id: parentId,
      type: 'droppable',
      parentId,
    },
  });
  return <div ref={setNodeRef}>{children}</div>;
};

interface InnerUnitsProps {
  editor: Editor;
  unit: UnitTreeNode;
}

/**
 * 指定された親ユニットの子ユニットを再帰的にレンダリングするコンポーネント
 */
const InnerUnits = ({ editor, unit }: InnerUnitsProps): JSX.Element => {
  const children = unit?.children ?? [];

  // グループ内へのユニット追加ハンドラ
  const handleInsert = useCallback(
    async (menuItem: UnitMenuItem) => {
      const newUnits = menuItem.units.map((unit) => {
        const { id, ...options } = unit;
        return editor.createUnit(id, options);
      });
      const position: UnitLastPosition = { index: undefined, rootId: unit.id };
      editor.commands.insertUnit(newUnits, position);
    },
    [editor, unit]
  );

  return (
    <>
      <UnitList editor={editor} units={children} />
      {children.length === 0 && (
        <EmptyDroppable parentId={unit.id}>
          <UnitInserter editor={editor} variant="unit-insert" visibility="visible" onInsert={handleInsert} />
        </EmptyDroppable>
      )}
    </>
  );
};

export default InnerUnits;
