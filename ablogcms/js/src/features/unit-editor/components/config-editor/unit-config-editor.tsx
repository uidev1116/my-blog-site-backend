import type { ConfigEditor, UnitConfigList } from '@features/unit-editor/core/types';
import HStack from '@components/stack/h-stack';
import { defaultConfigEditorSettings } from '@features/unit-editor/config';
import ConfigEditorContent from './config-editor-content';
import useConfigEditor from './hooks/use-config-editor';
import { UnitConfigEditorSettings } from './types';
import { SettingsProvider, useSettings } from './stores/settings';

interface UnitAppenderProps {
  editor: ConfigEditor;
}

const UnitAppender = ({ editor }: UnitAppenderProps) => {
  const { unitDefs } = useSettings();
  const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    const type = event.currentTarget.name;
    if (type === '') {
      return;
    }

    const name = unitDefs.find((def) => def.id === type)?.label;
    const newUnit = editor.create(type, { name });
    editor.insert(newUnit);
  };

  return (
    <HStack wrap="wrap" spacing="0.25rem" className="acms-admin-mb-3">
      {unitDefs.map((unitType) => (
        <button
          key={unitType.id}
          type="button"
          className="acms-admin-btn-admin"
          name={unitType.id}
          onClick={handleClick}
        >
          {unitType.label}
        </button>
      ))}
    </HStack>
  );
};

interface UnitConfigEditorProps {
  id: string;
  label: string;
  defaultValue?: UnitConfigList;
  settings?: UnitConfigEditorSettings;
}

const UnitConfigEditor = ({
  id,
  label,
  defaultValue = [],
  settings = defaultConfigEditorSettings,
}: UnitConfigEditorProps) => {
  const editor = useConfigEditor({ id, defaultValue });
  if (!editor) {
    return null;
  }

  return (
    <div className="acms-admin-unit-config-editor">
      <div>
        <h2 className="acms-admin-unit-config-editor-title">
          <span className="acms-admin-label">{label}</span>
        </h2>
      </div>
      <SettingsProvider settings={settings}>
        <div>
          <UnitAppender editor={editor} />
          <ConfigEditorContent editor={editor} />
        </div>
      </SettingsProvider>
    </div>
  );
};

export default UnitConfigEditor;
