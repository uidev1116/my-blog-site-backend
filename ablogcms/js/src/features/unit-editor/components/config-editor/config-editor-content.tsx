import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useMemo } from 'react';
import type { ConfigEditor } from '@features/unit-editor/core';
import ConfigEdit from './config-edit';

interface ConfigEditorContentProps {
  editor: ConfigEditor;
}

const ConfigEditorContent = ({ editor }: ConfigEditorContentProps) => {
  const { configs, move, flatten } = editor;

  const flattenedConfigs = useMemo(() => flatten(configs), [configs, flatten]);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (active.id !== over?.id) {
      const oldIndex = configs.findIndex((unit) => unit.id === active.id);
      const newIndex = configs.findIndex((unit) => unit.id === over?.id);

      if (oldIndex !== -1 && newIndex !== -1) {
        const unitId = active.id as string;
        move(unitId, newIndex);
      }
    }
  };

  const sortedIds = useMemo(() => flattenedConfigs.map(({ id }) => id), [flattenedConfigs]);

  return (
    <>
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={sortedIds} strategy={verticalListSortingStrategy}>
          <div className="acms-admin-unit-config-edit-list">
            {flattenedConfigs.map((config) => {
              return <ConfigEdit key={config.id} config={config} editor={editor} />;
            })}
          </div>
        </SortableContext>
      </DndContext>
      <input type="hidden" name="config[]" value={`${editor.namePrefix}type`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}align`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}group`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}size`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}edit`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}field_1`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}field_2`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}field_3`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}field_4`} />
      <input type="hidden" name="config[]" value={`${editor.namePrefix}field_5`} />
    </>
  );
};

export default ConfigEditorContent;
