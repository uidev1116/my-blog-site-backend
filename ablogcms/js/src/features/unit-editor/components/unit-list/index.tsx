import type { Editor, UnitTree } from '@features/unit-editor/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import Unit from '../unit';

interface UnitListProps {
  editor: Editor;
  units: UnitTree;
}

const UnitList = ({ editor, units = [] }: UnitListProps) => {
  return (
    <SortableContext items={units} strategy={verticalListSortingStrategy}>
      {units.map((unit) => (
        <Unit key={unit.id} editor={editor} unit={unit} />
      ))}
    </SortableContext>
  );
};

export default UnitList;
