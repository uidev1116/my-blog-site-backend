import { useSortable } from '@dnd-kit/sortable';
import classnames from 'classnames';
import { CSS } from '@dnd-kit/utilities';
import type { Editor, UnitMenuItem } from '@features/unit-editor/core';
import type { UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { useMemo, useCallback } from 'react';
import UnitInserter from '../unit-inserter';

interface UnitProps {
  editor: Editor;
  unit: UnitTreeNode;
}

const BASE_CLASSNAME = 'acms-admin-unit';

/**
 * ユニットコンポーネント
 */
const Unit = ({ editor, unit }: UnitProps) => {
  const { setNodeRef, transform, transition, isDragging, setActivatorNodeRef, attributes, listeners } = useSortable({
    id: unit.id,
    data: {
      id: unit.id,
      type: 'item',
      parentId: editor.selectors.findParentUnit(unit.id)?.id || null,
    },
    transition: null,
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

  // ユニットクリック時の選択処理
  const handleClick = useCallback(
    (event: React.MouseEvent<HTMLDivElement>) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }

      const clickedUnit = event.target.closest<HTMLElement>(`.${BASE_CLASSNAME}`);
      if (clickedUnit === null) {
        return;
      }

      if (clickedUnit.dataset.unitId !== unit.id) {
        return;
      }

      editor.commands.selectUnit(unit.id);
    },
    [editor, unit.id]
  );

  // キーボードナビゲーション処理
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent) => {
      // フォーカスがUnit自身に無ければ無視（入力フォーム内での矢印キーを除外）
      if (event.target !== event.currentTarget) {
        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        event.stopPropagation();
        editor.commands.selectNextUnit();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        event.stopPropagation();
        editor.commands.selectPreviousUnit();
      }
    },
    [editor]
  );

  // ユニット後に追加するハンドラ
  const handleInsert = useCallback(
    async (menuItem: UnitMenuItem) => {
      const newUnits = menuItem.units.map((unit) => {
        const { id, ...options } = unit;
        return editor.createUnit(id, options);
      });
      editor.commands.insertAfterUnit(unit.id, newUnits);
    },
    [editor, unit]
  );

  const unitDef = editor.findUnitDef(unit.type);

  if (!unitDef) {
    return null;
  }

  const Edit = unitDef.edit;

  return (
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions
    <div
      ref={setNodeRef}
      style={style}
      className={classnames(BASE_CLASSNAME, {
        'acms-admin-unit-hidden': unit.status === 'close',
        'acms-admin-unit-dragging': isDragging,
        'acms-admin-unit-selected': editor.selectors.isSelectedUnit(unit.id), // 選択状態のスタイル
      })}
      onClick={handleClick} // クリックで選択
      onKeyDown={handleKeyDown}
      tabIndex={-1} // JavaScriptで選択状態を制御するために必要
      data-unit-id={unit.id}
      data-unit-type={unit.type}
      data-unit-status={unit.status}
      data-unit-collapsed={unit.collapsed}
      data-unit-align={unit.align}
      data-unit-group={unit.group}
      data-unit-anker={unit.anker}
      data-unit-children-count={unit.children.length}
      data-unit-hidden={unit.status === 'close'}
      data-unit-dragging={isDragging}
      data-unit-selected={editor.selectors.isSelectedUnit(unit.id)}
    >
      <Edit editor={editor} unit={unit} handleProps={handleProps} />

      {!editor.selectors.isLastPositionUnit(unit.id) && <UnitInserter editor={editor} onInsert={handleInsert} />}
      <input key={`unit-id-${unit.id}`} type="hidden" name="unit_id[]" defaultValue={unit.id} />
      <input
        key={`unit-id-${unit.id}-${editor.selectors.findParentUnit(unit.id)?.id}`}
        type="hidden"
        name="unit_parent_id[]"
        defaultValue={editor.selectors.findParentUnit(unit.id)?.id}
      />
      <input key={`unit-type-${unit.id}-${unit.type}`} type="hidden" name="unit_type[]" defaultValue={unit.type} />
      <input key={`unit-align-${unit.id}-${unit.align}`} type="hidden" name="unit_align[]" defaultValue={unit.align} />
      <input key={`unit-group-${unit.id}-${unit.group}`} type="hidden" name="unit_group[]" defaultValue={unit.group} />
      <input
        key={`unit-status-${unit.id}-${unit.status}`}
        type="hidden"
        name="unit_status[]"
        defaultValue={unit.status}
      />
      <input key={`unit-anker-${unit.id}-${unit.anker}`} type="hidden" name="unit_anker[]" defaultValue={unit.anker} />
    </div>
  );
};

export default Unit;
