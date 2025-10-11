import type { UnitTreeNode, UnitLastPosition, UnitPosition, UnitTree } from '@features/unit-editor/core/types/unit';
import Editor from '../editor';
import { findUnitById, findUnitPosition, getNextUnit, getPreviousUnit } from './selectors';
import * as validators from './validators';
import { replaceLast } from '../../../../utils/string';
import { EditorState } from '../types';

/**
 * 指定位置にユニットを挿入する
 * @param editor エディタインスタンス
 * @param newUnit 追加するユニット
 * @param position ユニットを挿入する位置
 * @returns 更新されたEditorState
 */
export const insertUnit = (
  editor: Editor,
  newUnit: UnitTreeNode | UnitTree,
  position?: UnitPosition | UnitLastPosition
): EditorState => {
  if (position != null && position.index != null && position.index < 0) {
    throw new RangeError('index must be greater than 0');
  }

  const newUnits = Array.isArray(newUnit) ? newUnit : [newUnit];
  for (const unit of newUnits) {
    const result = validators.validateUnitInsert(editor, unit, position as UnitPosition);
    if (!result.valid) {
      editor.emit('error', {
        editor,
        code: 'unitInsertFailed',
        message: result.reason || 'Unit insertion validation failed',
        unit,
      });
      return editor.state; // バリデーションエラー時は現在のunitsを返す
    }
  }

  const insertAt = (arr: UnitTree, items: UnitTree, idx: number = arr.length) => {
    if (idx > arr.length) {
      throw new RangeError('index must be less than the number of units');
    }
    return [...arr.slice(0, idx), ...items, ...arr.slice(idx)];
  };

  const recursiveInsert = (tree: UnitTree): UnitTree => {
    return tree.map((node) => {
      if (node.id === position?.rootId) {
        const children = node.children ?? [];
        return {
          ...node,
          children: insertAt(children, newUnits, position?.index),
        };
      }

      if (node.children) {
        return {
          ...node,
          children: recursiveInsert(node.children),
        };
      }

      return node;
    });
  };

  if (!position?.rootId) {
    return {
      ...editor.state,
      units: insertAt(editor.state.units, newUnits, position?.index),
      selectedUnitIds: [newUnits[0].id],
    };
  }

  return {
    ...editor.state,
    units: recursiveInsert(editor.state.units),
    selectedUnitIds: [newUnits[0].id],
  };
};

/**
 * 指定したユニットの後にユニットを挿入する
 * @param editor エディタインスタンス
 * @param id 基準となるユニットのID
 * @param newUnit 追加するユニット
 * @returns 更新されたEditorState
 */
export const insertAfterUnit = (
  editor: Editor,
  id: UnitTreeNode['id'],
  newUnit: UnitTreeNode | UnitTree
): EditorState => {
  const position = findUnitPosition(editor, id);
  if (!position) {
    return editor.state;
  }

  const newPosition: UnitPosition = { index: position.index + 1, rootId: position.rootId };
  return insertUnit(editor, newUnit, newPosition);
};

/**
 * 指定したユニットの前にユニットを挿入する
 * @param editor エディタインスタンス
 * @param id 基準となるユニットのID
 * @param newUnit 追加するユニット
 * @returns 更新されたEditorState
 */
export const insertBeforeUnit = (
  editor: Editor,
  id: UnitTreeNode['id'],
  newUnit: UnitTreeNode | UnitTree
): EditorState => {
  const position = findUnitPosition(editor, id);
  if (!position) {
    return editor.state;
  }

  return insertUnit(editor, newUnit, position);
};

/**
 * ユニットを削除する
 * @param editor エディタインスタンス
 * @param id 削除するユニットのID
 * @returns 更新されたEditorState
 */
export const removeUnit = (editor: Editor, id: UnitTreeNode['id'] | UnitTreeNode['id'][]): EditorState => {
  /**
   * 再帰的にユニットを削除する
   * @param units ユニット配列
   * @param id 削除するユニットのID
   * @returns 更新されたユニット配列
   */
  const removeUnitRecursive = (units: UnitTree, id: UnitTreeNode['id'] | UnitTreeNode['id'][]): UnitTree => {
    return units
      .filter((unit) => {
        if (Array.isArray(id)) {
          return !id.includes(unit.id);
        }
        return unit.id !== id;
      })
      .map((unit) => {
        if (unit.children) {
          return {
            ...unit,
            children: removeUnitRecursive(unit.children, id),
          };
        }
        return unit;
      });
  };
  return {
    ...editor.state,
    units: removeUnitRecursive(editor.state.units, id),
    selectedUnitIds: [],
  };
};

/**
 * ユニットを更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param data 更新データ
 * @returns 更新されたEditorState
 */
export const updateUnit = (editor: Editor, id: UnitTreeNode['id'], data: Partial<UnitTreeNode>): EditorState => {
  /**
   * 再帰的にユニットを更新する
   * @param units ユニット配列
   * @param id 更新するユニットのID
   * @param data 更新データ
   * @returns 更新されたユニット配列
   */
  const updateUnitRecursive = (units: UnitTree, id: UnitTreeNode['id'], data: Partial<UnitTreeNode>): UnitTree => {
    return units.map((unit) => {
      if (unit.id === id) {
        return { ...unit, ...data };
      }
      if (unit.children) {
        return {
          ...unit,
          children: updateUnitRecursive(unit.children, id, data),
        };
      }
      return unit;
    });
  };
  return {
    ...editor.state,
    units: updateUnitRecursive(editor.state.units, id, data),
  };
};

/**
 * ユニットのステータスを更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param status ステータス
 * @returns 更新されたEditorState
 */
export const setUnitStatus = (editor: Editor, id: UnitTreeNode['id'], status: UnitTreeNode['status']): EditorState => {
  return updateUnit(editor, id, { status });
};

/**
 * ユニットのステータスをトグルする
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @returns 更新されたEditorState
 */
export const toggleUnitStatus = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const unit = findUnitById(editor, id);
  if (unit === null) {
    return editor.state;
  }
  const status = unit.status === 'open' ? 'close' : 'open';
  return setUnitStatus(editor, id, status);
};

/**
 * ユニットの並び順を変更する
 * @param editor エディタインスタンス
 * @param id 移動するユニットのID
 * @param editor エディタインスタンス
 * @param newPosition 新しい位置
 * @returns 更新されたユニット配列
 */
export const moveUnitToPosition = (editor: Editor, id: UnitTreeNode['id'], newPosition: UnitPosition): EditorState => {
  if (id === newPosition.rootId) {
    // 移動先が自分自身の場合は何もしない
    return editor.state;
  }

  // 移動対象のユニットを探す
  const unit = findUnitById(editor, id);
  if (unit === null) {
    return editor.state;
  }

  const validation = validators.validateUnitMove(editor, unit, newPosition);
  if (!validation.valid) {
    editor.emit('error', {
      editor,
      code: 'unitMoveFailed',
      message: validation.reason || 'Unit move validation failed',
      unit,
    });
    return editor.state; // バリデーションエラー時は現在のEditorStateを返す
  }

  editor.chain().removeUnit(id).insertUnit(unit, newPosition).run();
  return editor.state;
};

/**
 * ユニットを上に移動する
 * @param editor エディタインスタンス
 * @param id 移動するユニットのID
 * @returns 更新されたEditorState
 */
export const moveUpUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const currentPosition = findUnitPosition(editor, id);
  if (currentPosition === null) {
    return editor.state;
  }
  const newPosition: UnitPosition = { ...currentPosition, index: currentPosition.index - 1 };
  return moveUnitToPosition(editor, id, newPosition);
};

/**
 * ユニットを下に移動する
 * @param editor エディタインスタンス
 * @param id 移動するユニットのID
 * @returns 更新されたEditorState
 */
export const moveDownUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const currentPosition = findUnitPosition(editor, id);
  if (currentPosition === null) {
    return editor.state;
  }
  const newPosition: UnitPosition = { ...currentPosition, index: currentPosition.index + 1 };
  return moveUnitToPosition(editor, id, newPosition);
};

/**
 * ユニットの配置を更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param align 配置
 * @returns 更新されたEditorState
 */
export const setUnitAlign = (
  editor: Editor,
  id: UnitTreeNode['id'],
  align: NonNullable<UnitTreeNode['align']>
): EditorState => {
  return updateUnit(editor, id, { align });
};

/**
 * ユニットのグループを更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param group グループ
 * @returns 更新されたEditorState
 */
export const setUnitGroup = (
  editor: Editor,
  id: UnitTreeNode['id'],
  group: NonNullable<UnitTreeNode['group']>
): EditorState => {
  return updateUnit(editor, id, { group });
};

/**
 * ユニットの折りたたみ状態を更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param collapsed 折りたたみ状態
 * @returns 更新されたEditorState
 */
export const setUnitCollapsed = (
  editor: Editor,
  id: UnitTreeNode['id'],
  collapsed: UnitTreeNode['collapsed']
): EditorState => {
  return updateUnit(editor, id, { collapsed });
};

/**
 * ユニットの折りたたみ状態をトグルする
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @returns 更新されたEditorState
 */
export const toggleUnitCollapsed = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const unit = findUnitById(editor, id);
  if (unit === null) {
    return editor.state;
  }
  const collapsed = !unit.collapsed;
  return setUnitCollapsed(editor, id, collapsed);
};

/**
 * ユニットのアンカーを更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param anker アンカー
 * @returns 更新されたEditorState
 */
export const setUnitAnker = (
  editor: Editor,
  id: UnitTreeNode['id'],
  anker: NonNullable<UnitTreeNode['anker']>
): EditorState => {
  return updateUnit(editor, id, { anker });
};

/**
 * ユニットの属性を更新する
 * @param editor エディタインスタンス
 * @param id 更新するユニットのID
 * @param attributes 属性
 * @returns 更新されたEditorState
 */
export const setUnitAttributes = (
  editor: Editor,
  id: UnitTreeNode['id'],
  attributes: Partial<Exclude<UnitTreeNode['attributes'], string>>
): EditorState => {
  const unit = findUnitById(editor, id);
  if (unit === null) {
    return editor.state;
  }

  const newAttributes = { ...unit.attributes, ...attributes };
  return updateUnit(editor, id, { attributes: newAttributes });
};

/**
 * ユニットを複製する
 * @param editor エディタインスタンス
 * @param id 複製するユニットのID
 * @returns 更新されたユニット配列
 */
export const duplicateUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const unit = findUnitById(editor, id);
  if (unit === null) {
    throw new Error('Duplicate unit failed because unit not found');
  }

  const validation = validators.validateUnitDuplicate(editor, unit);
  if (!validation.valid) {
    editor.emit('error', {
      editor,
      code: 'unitDuplicateFailed',
      message: validation.reason || 'Unit duplicate validation failed',
      unit,
    });
    return editor.state;
  }

  const duplicateUnitRecursive = (unit: UnitTreeNode): UnitTreeNode => {
    const newId = editor.generateUnitId();

    // ユニット定義を取得
    const newAttributes = { ...unit.attributes };

    // ユニットIDを含むキーを新しいIDに変更する
    Object.keys(newAttributes).forEach((key) => {
      if (key.includes(unit.id)) {
        const newKey = replaceLast(key, unit.id, newId);
        newAttributes[newKey] = newAttributes[key];
        delete newAttributes[key];
      }
    });

    // - キーが@で始まる値（フィールドグループ）
    // - キーがunitで始まる値（カスタムユニット）
    // 上記に当てはまるユニットIDを含む値を新しいIDに変更する
    Object.entries(newAttributes)
      .filter(([key]) => key.startsWith('@') || key.startsWith('unit'))
      .forEach(([key, value]) => {
        if (Array.isArray(value)) {
          // 配列の場合は、配列の要素を新しいIDに変更する
          const newValue = value.map((valueItem) => {
            if (valueItem.includes(unit.id)) {
              const newValueItem = replaceLast(valueItem, unit.id, newId);
              return newValueItem;
            }
            return valueItem;
          });
          newAttributes[key] = newValue;
        }

        if (typeof value === 'string' && value.includes(unit.id)) {
          const newValue = replaceLast(value, unit.id, newId);
          newAttributes[key] = newValue;
        }
      });

    return {
      ...unit,
      id: newId,
      attributes: newAttributes,
      children: unit.children.map(duplicateUnitRecursive),
    };
  };
  const newUnit = duplicateUnitRecursive(unit);
  const position = findUnitPosition(editor, id);
  if (position === null) {
    throw new Error('Duplicate unit failed because position not found');
  }
  return insertUnit(editor, newUnit, { ...position, index: position.index + 1 });
};

/**
 * ユニットを選択する
 * @param editor エディタインスタンス
 * @param id 選択するユニットのID
 * @returns 更新されたEditorState
 */
export const selectUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  return {
    ...editor.state,
    selectedUnitIds: [id],
  };
};

/**
 * ユニットの選択を解除する
 * @param editor エディタインスタンス
 * @param id 選択解除するユニットのID
 * @returns 更新されたEditorState
 */
export const deselectUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  return {
    ...editor.state,
    selectedUnitIds: editor.state.selectedUnitIds.filter((selectedId) => selectedId !== id),
  };
};

/**
 * すべての選択を解除する
 * @param editor エディタインスタンス
 * @returns 更新されたEditorState
 */
export const deselectAll = (editor: Editor): EditorState => {
  return {
    ...editor.state,
    selectedUnitIds: [],
  };
};

/**
 * 次のユニットを選択する
 * @param editor エディタインスタンス
 * @returns 更新された選択ユニットID配列
 */
export const selectNextUnit = (editor: Editor): EditorState => {
  const nextUnit = getNextUnit(editor);
  return {
    ...editor.state,
    selectedUnitIds: nextUnit ? [nextUnit.id] : [],
  };
};

/**
 * 前のユニットを選択する
 * @param editor エディタインスタンス
 * @returns 更新された選択ユニットID配列
 */
export const selectPreviousUnit = (editor: Editor): EditorState => {
  const previousUnit = getPreviousUnit(editor);
  return {
    ...editor.state,
    selectedUnitIds: previousUnit ? [previousUnit.id] : [],
  };
};

export const wrapUnits = (editor: Editor, unit: UnitTreeNode, ids: UnitTreeNode['id'][]): EditorState => {
  if (ids.length === 0) {
    return editor.state;
  }

  const validation = validators.validateUnitWrap(editor, ids);
  if (!validation.valid) {
    editor.emit('error', {
      editor,
      code: 'wrapUnitsFailed',
      message: validation.reason || 'Unit wrap validation failed',
      unit,
    });
    return editor.state;
  }

  const position = findUnitPosition(editor, ids[0]);
  if (position === null) {
    return editor.state;
  }

  const newUnits = ids.map((id) => findUnitById(editor, id)).filter((u): u is UnitTreeNode => u !== null);
  unit.children = newUnits;

  editor.chain().removeUnit(ids).insertUnit(unit, { index: position.index, rootId: position.rootId }).run();

  return editor.state;
};

/**
 * unwrap a group unit – move its children to parent level and remove the group
 */
export const unwrapUnit = (editor: Editor, id: UnitTreeNode['id']): EditorState => {
  const unit = findUnitById(editor, id);
  if (unit === null) {
    return editor.state;
  }

  const validation = validators.validateUnitUnwrap(editor, unit);
  if (!validation.valid) {
    editor.emit('error', {
      editor,
      code: 'unwrapUnitFailed',
      message: validation.reason || 'Unit unwrap validation failed',
      unit,
    });
    return editor.state;
  }

  const position = findUnitPosition(editor, id);
  if (position === null) {
    return editor.state;
  }

  editor.chain().removeUnit(id).insertUnit(unit.children, { index: position.index, rootId: position.rootId }).run();
  return editor.state;
};
