import type { UnitTree } from '@features/unit-editor/core/types/unit';
import useUnitEditor from '@features/unit-editor/hooks/use-unit-editor';
import { Editor, type EditorOptions, coreCommands, coreSelectors, coreUnitDefs } from '@features/unit-editor/core';
import { useCallback, useRef, useState } from 'react';
import { SettingsProvider } from '@features/unit-editor/stores/settings';
import type { UnitEditorSettings } from '@features/unit-editor/types';
import { defaultEditorSettings } from '@features/unit-editor/config';
import { notify } from '../../../../lib/notify';
import EditorContent from '../editor-content';

interface UnitEditorProps {
  defaultValue?: UnitTree;
  settings?: UnitEditorSettings;
  onCreate?: (editor: Editor) => void;
  onContentChange?: (data: UnitTree) => void;
}

/**
 * ユニットエディターのメインコンポーネント
 */
const UnitEditor = ({
  defaultValue = [],
  settings = defaultEditorSettings,
  onCreate,
  onContentChange,
}: UnitEditorProps) => {
  const [overriddenSettings, setOverriddenSettings] = useState<Partial<UnitEditorSettings>>({});
  const containerRef = useRef<HTMLDivElement>(null);
  const handleCreate: EditorOptions['onCreate'] = useCallback(
    async ({ editor }) => {
      onCreate?.(editor);
      if (settings.unitGroup.enable) {
        // 階層を持つユニットが存在する場合はユニットグループを無効にする
        // （tree構造とユニットグループは同時に利用できない）
        const someParentUnit = editor
          .flatten(editor.state.units)
          .some((unit) => editor.selectors.findParentUnit(unit.id));
        if (someParentUnit) {
          setOverriddenSettings({
            unitGroup: {
              enable: false,
              options: [],
            },
          });
          await ACMS.Library.dialog.alert('階層を持つユニットが存在するため、ユニットグループを無効にします。');
        }
      }
      const dom = containerRef.current ?? document;
      ACMS.dispatchEvent('acmsUnitEditorCreate', dom, {
        editor,
        settings,
      });
    },
    [settings, onCreate]
  );
  const handleTransaction: EditorOptions['onTransaction'] = useCallback(
    (props) => {
      const { editor } = props;
      const data = editor.state.units;
      onContentChange?.(data);
    },
    [onContentChange]
  );
  const handleError: EditorOptions['onError'] = useCallback((props) => {
    const { message } = props;
    notify.danger(message);
  }, []);

  const handleServerSideUnitRender: EditorOptions['onServerSideUnitRender'] = useCallback(({ element, ...props }) => {
    // v3.2 未満との互換性を保つため、ここで acmsAddUnit を呼び出す
    ACMS.dispatchEvent('acmsAddUnit', element, {
      type: 'unit-editor',
      item: element,
      ...props,
    });
  }, []);

  const editor = useUnitEditor({
    units: defaultValue,
    unitDefs: coreUnitDefs,
    commands: coreCommands,
    selectors: coreSelectors,
    onCreate: handleCreate,
    onTransaction: handleTransaction,
    onError: handleError,
    onServerSideUnitRender: handleServerSideUnitRender,
  });

  if (!editor) {
    return null;
  }
  return (
    <SettingsProvider settings={{ ...settings, ...overriddenSettings }}>
      <div ref={containerRef} className="acms-admin-unit-editor">
        <EditorContent editor={editor} />
      </div>
    </SettingsProvider>
  );
};

export default UnitEditor;
