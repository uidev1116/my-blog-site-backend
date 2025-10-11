import { Icon } from '@components/icon';
import HStack from '@components/stack/h-stack';
import VisuallyHidden from '@components/visually-hidden';
import type { UnitConfigListItem } from '@features/unit-editor/core/types';
import { useCallback } from 'react';
import { ConfigEditor } from '@features/unit-editor/core';

interface ConfigEditHeaderProps {
  config: UnitConfigListItem;
  editor: ConfigEditor;
}

const ConfigEditHeader = ({ config, editor }: ConfigEditHeaderProps) => {
  const handleDelete = useCallback(() => {
    editor.remove(config.id);
  }, [editor, config.id]);

  const handleEdit = useCallback(() => {
    editor.update(config.id, (prev) => ({ ...prev, collapsed: !prev.collapsed }));
  }, [editor, config.id]);

  const unitDef = editor.editor.findUnitDef(config.type);

  if (!unitDef) {
    return null;
  }

  return (
    <div className="acms-admin-unit-config-edit-header">
      <div>
        <h3 className="acms-admin-unit-config-edit-title">{config.name}</h3>
      </div>
      <div>
        <HStack>
          {unitDef.config !== undefined && (
            <button
              type="button"
              className="acms-admin-btn acms-admin-btn-icon"
              onClick={handleEdit}
              aria-expanded={!config.collapsed}
              aria-controls={`acms-config-edit-body-${config.id}`}
            >
              <Icon name="edit" />
              <VisuallyHidden>編集する</VisuallyHidden>
            </button>
          )}
          <button
            type="button"
            className="acms-admin-btn acms-admin-btn-icon acms-admin-btn-danger"
            onClick={handleDelete}
          >
            <Icon name="delete" />
            <VisuallyHidden>削除する</VisuallyHidden>
          </button>
        </HStack>
      </div>
    </div>
  );
};

export default ConfigEditHeader;
