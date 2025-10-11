import { useMemo } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import classnames from 'classnames';
import type { UnitConfigListItem } from '@features/unit-editor/core/types';
import { ConfigEditor } from '@features/unit-editor/core';
import ConfigEditHandle from './config-edit-handle';
import ConfigEditHeader from './config-edit-header';

interface ConfigEditProps {
  config: UnitConfigListItem;
  editor: ConfigEditor;
}

const ConfigEdit = ({ config, editor }: ConfigEditProps) => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: config.id,
  });

  const style = {
    transform: CSS.Translate.toString(transform),
    transition,
    position: 'relative' as const,
  };

  const handleProps = useMemo(
    () => ({
      ref: setActivatorNodeRef,
      ...attributes,
      ...listeners,
    }),
    [setActivatorNodeRef, attributes, listeners]
  );

  const unitDef = editor.editor.findUnitDef(config.type);

  if (!unitDef) {
    return null;
  }

  const Edit = unitDef.config;

  return (
    <div
      className={classnames('acms-admin-unit-config-edit', {
        'acms-admin-dragging': isDragging,
      })}
      ref={setNodeRef}
      style={style}
      data-config-id={config.id}
      data-config-type={config.type}
      data-config-name={config.name}
      data-config-collapsed={config.collapsed}
    >
      <ConfigEditHandle handleProps={handleProps} />
      <ConfigEditHeader config={config} editor={editor} />
      {Edit !== undefined && config.collapsed === false && (
        <div id={`acms-config-edit-body-${config.id}`} className="acms-admin-unit-config-edit-body">
          <Edit config={config} editor={editor} />
        </div>
      )}
      <input type="hidden" name={`${editor.namePrefix}align[]`} defaultValue={config.align} />
      <input type="hidden" name={`${editor.namePrefix}size[]`} defaultValue={config.size} />
      <input type="hidden" name={`${editor.namePrefix}edit[]`} defaultValue={config.edit} />
      <input type="hidden" name={`${editor.namePrefix}field_1[]`} defaultValue={config.field_1} />
      <input type="hidden" name={`${editor.namePrefix}field_2[]`} defaultValue={config.field_2} />
      <input type="hidden" name={`${editor.namePrefix}field_3[]`} defaultValue={config.field_3} />
      <input type="hidden" name={`${editor.namePrefix}field_4[]`} defaultValue={config.field_4} />
      <input type="hidden" name={`${editor.namePrefix}field_5[]`} defaultValue={config.field_5} />
      <input type="hidden" name={`${editor.namePrefix}group[]`} defaultValue={config.group} />
      <input type="hidden" name={`${editor.namePrefix}type[]`} defaultValue={config.type} />
    </div>
  );
};

export default ConfigEdit;
