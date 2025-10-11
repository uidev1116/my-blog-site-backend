import classnames from 'classnames';
import { useCallback } from 'react';
import {
  type EditorOptions,
  type UnitTree,
  type UnitTreeNode,
  coreCommands,
  coreSelectors,
  coreUnitDefs,
} from '@features/unit-editor/core';
import useUnitEditor from '@features/unit-editor/hooks/use-unit-editor';
import { UnitEditorSettings } from '@features/unit-editor/types';
import { defaultEditorSettings } from '@features/unit-editor/config';
import { SettingsProvider } from '@features/unit-editor/stores/settings';
import HStack from '@components/stack/h-stack';
import Alert from '@components/alert/alert';
import { notify } from '../../../../lib/notify';

interface InplaceEditorProps {
  defaultValue?: UnitTree;
  settings?: UnitEditorSettings;
  unitId: UnitTreeNode['id'];
}

const BASE_CLASSNAME = 'acms-admin-unit';

const InplaceEditor = ({ defaultValue = [], settings = defaultEditorSettings, unitId }: InplaceEditorProps) => {
  const handleError: EditorOptions['onError'] = useCallback((props) => {
    const { message } = props;
    notify.danger(message);
  }, []);

  const handleServerSideUnitRender: EditorOptions['onServerSideUnitRender'] = useCallback(({ element, ...props }) => {
    // v3.2 未満との互換性を保つため、ここで acmsAddUnit を呼び出す
    ACMS.dispatchEvent('acmsAddUnit', element, {
      item: element,
      ...props,
    });
  }, []);

  const editor = useUnitEditor({
    units: defaultValue,
    commands: coreCommands,
    selectors: coreSelectors,
    unitDefs: coreUnitDefs,
    onError: handleError,
    onServerSideUnitRender: handleServerSideUnitRender,
  });

  if (!editor) {
    return null;
  }

  const unit = editor.selectors.findUnitById(unitId);

  if (!unit) {
    throw new Error(`Could not find the unit with ID "${unitId}"`);
  }

  const unitDef = editor.findUnitDef(unit.type);

  if (!unitDef) {
    throw new Error(`Could not find the definition for unit type "${unit.type}"`);
  }

  const Edit = unitDef.inplaceEdit;

  if (Edit === undefined) {
    return (
      <div className="acms-admin-unit-error">
        <Alert type="danger">このユニットはダイレクト編集に対応していません。</Alert>
        <HStack justify="end">
          <button type="button" className="acms-admin-btn-admin">
            キャンセル
          </button>
        </HStack>
      </div>
    );
  }

  return (
    <SettingsProvider settings={settings}>
      <div
        className={classnames(BASE_CLASSNAME, {
          'acms-admin-unit-hidden': unit.status === 'close',
        })}
        data-unit-id={unit.id}
        data-unit-type={unit.type}
        data-unit-status={unit.status}
        data-unit-collapsed={unit.collapsed}
        data-unit-align={unit.align}
        data-unit-group={unit.group}
        data-unit-anker={unit.anker}
        data-unit-hidden={unit.status === 'close'}
        data-unit-children-count={unit.children.length}
      >
        <Edit editor={editor} unit={unit} />
        <div className="acms-admin-unit-footer">
          <HStack
            // エンターキーで保存されるようにするため、キャンセルボタンを右に配置して、row-reverse にすることで保存ボタンを右に配置する
            direction="row-reverse"
          >
            <button
              type="submit"
              name="intent"
              value="save"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-btn-admin-save"
            >
              保存
            </button>
            <button type="submit" name="intent" value="cancel" className="acms-admin-btn-admin">
              キャンセル
            </button>
          </HStack>
        </div>

        <input key={`unit-id-${unit.id}`} type="hidden" name="unit_id[]" defaultValue={unit.id} />
        <input
          key={`unit-id-${unit.id}-${editor.selectors.findParentUnit(unit.id)?.id}`}
          type="hidden"
          name="unit_parent_id[]"
          defaultValue={editor.selectors.findParentUnit(unit.id)?.id}
        />
        <input key={`unit-type-${unit.id}-${unit.type}`} type="hidden" name="unit_type[]" defaultValue={unit.type} />
        <input
          key={`unit-align-${unit.id}-${unit.align}`}
          type="hidden"
          name="unit_align[]"
          defaultValue={unit.align}
        />
        <input
          key={`unit-group-${unit.id}-${unit.group}`}
          type="hidden"
          name="unit_group[]"
          defaultValue={unit.group}
        />
        <input
          key={`unit-status-${unit.id}-${unit.status}`}
          type="hidden"
          name="unit_status[]"
          defaultValue={unit.status}
        />
        <input
          key={`unit-anker-${unit.id}-${unit.anker}`}
          type="hidden"
          name="unit_anker[]"
          defaultValue={unit.anker}
        />
        <input
          key={`unit-sort-${unit.id}-${editor.selectors.findUnitIndex(unit.id)}`}
          type="hidden"
          name="unit_sort[]"
          defaultValue={editor.selectors.findUnitIndex(unit.id)!}
        />
      </div>
    </SettingsProvider>
  );
};

export default InplaceEditor;
