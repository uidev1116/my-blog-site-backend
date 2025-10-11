import type { Editor, UnitAlignOption, UnitAlignVersion } from '@features/unit-editor/core';
import type { UnitPosition, UnitTreeNode, UnitTree } from '@features/unit-editor/core/types/unit';
import {
  validateUnitDuplicate,
  validateUnitInsert,
  validateUnitMove,
  validateUnitUnwrap,
  validateUnitWrap,
} from './validators';

/**
 * 再帰的にユニットを検索する
 * @param editor エディタインスタンス
 * @param id 検索するユニットのID
 * @returns 見つかったユニット、または null
 */
export const findUnitById = (editor: Editor, id: UnitTreeNode['id']): UnitTreeNode | null => {
  const findUnitByIdRecursive = (units: UnitTree, id: UnitTreeNode['id']): UnitTreeNode | null => {
    for (const unit of units) {
      if (unit.id === id) {
        return unit;
      }
      if (unit.children) {
        const found = findUnitByIdRecursive(unit.children, id);
        if (found) {
          return found;
        }
      }
    }
    return null;
  };
  return findUnitByIdRecursive(editor.state.units, id);
};

/**
 * 再帰的にユニットの位置を探す
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns ユニットの位置（見つからない場合は null）
 */
export const findUnitPosition = (editor: Editor, id: UnitTreeNode['id']): UnitPosition | null => {
  // 現在のレベルでユニットを探す
  const index = editor.state.units.findIndex((unit) => unit.id === id);
  if (index !== -1) {
    return { index, rootId: undefined };
  }

  const findUnitPositionRecursive = (
    units: UnitTree,
    id: UnitTreeNode['id'],
    parentId?: UnitTreeNode['id']
  ): UnitPosition | null => {
    for (const unit of units) {
      const index = units.findIndex((child) => child.id === id);
      if (index !== -1) {
        return { index, rootId: parentId };
      }
      if (unit.children) {
        const result = findUnitPositionRecursive(unit.children, id, unit.id);
        if (result) {
          return result;
        }
      }
    }
    return null;
  };

  return findUnitPositionRecursive(editor.state.units, id, undefined);
};

/**
 * ユニットのindexを取得する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns ユニットのindex（見つからない場合は null）
 */
export const findUnitIndex = (editor: Editor, id: UnitTreeNode['id']): number | null => {
  const position = findUnitPosition(editor, id);
  if (position === null) {
    throw new Error(`Unit with id ${id} not found`);
  }

  return position.index;
};

/**
 * 親ユニットを取得する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns 親ユニット（見つからない場合は null）
 */
export const findParentUnit = (editor: Editor, id: UnitTreeNode['id']): UnitTreeNode | null => {
  const findParentRecursive = (units: UnitTree): UnitTreeNode | null => {
    for (const unit of units) {
      if (unit.children?.some((child) => child.id === id)) {
        return unit;
      }
      if (unit.children) {
        const found = findParentRecursive(unit.children);
        if (found) {
          return found;
        }
      }
    }
    return null;
  };

  return findParentRecursive(editor.state.units);
};

/**
 * 最初のpositionのユニットかどうか判定する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns 最初のpositionのユニットかどうか
 */
export const isFirstPositionUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const position = findUnitPosition(editor, id);
  if (position === null) {
    throw new Error(`Unit with id ${id} not found`);
  }
  if (position.rootId !== undefined) {
    // 親がある場合は最初のpositionではない
    return false;
  }
  return editor.state.units.at(0)?.id === id;
};

/**
 * 最後のpositionのユニットかどうか判定する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns 最後のpositionのユニットかどうか
 */
export const isLastPositionUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const position = findUnitPosition(editor, id);
  if (position === null) {
    throw new Error(`Unit with id ${id} not found`);
  }
  if (position.rootId !== undefined) {
    // 親がある場合は最後のpositionではない
    return false;
  }
  return editor.state.units.at(-1)?.id === id;
};

/**
 * 最初のindexのユニットかどうか判定する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns 最初のindexのユニットかどうか
 */
export const isFirstIndexUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const parent = findParentUnit(editor, id);
  if (parent && parent.children) {
    return parent.children.at(0)?.id === id;
  }
  return editor.state.units.at(0)?.id === id;
};

/**
 * 最後のindexのユニットかどうか判定する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @returns 最後のindexのユニットかどうか
 */
export const isLastIndexUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const parent = findParentUnit(editor, id);
  if (parent && parent.children) {
    return parent.children.at(-1)?.id === id;
  }
  return editor.state.units.at(-1)?.id === id;
};

/**
 * 祖先に指定したステータスのユニットが存在するかを判定する
 * @param editor エディタインスタンス
 * @param id 探すユニットのID
 * @param status 探すステータス
 * @returns 祖先に指定したステータスのユニットが存在するかどうか
 */
export const hasAncestorWithStatus = (
  editor: Editor,
  id: UnitTreeNode['id'],
  status: UnitTreeNode['status']
): boolean => {
  const parent = findParentUnit(editor, id);
  if (!parent) {
    return false;
  }
  if (parent.status === status) {
    return true;
  }
  return hasAncestorWithStatus(editor, parent.id, status);
};

/**
 * 現在選択されているユニットIDを取得する
 * @param editor エディタインスタンス
 * @returns 選択されているユニットID、または null
 */
export const getSelectedUnitIds = (editor: Editor): UnitTreeNode['id'][] => {
  return editor.state.selectedUnitIds;
};

/**
 * 指定したユニットが選択されているかを判定する
 * @param editor エディタインスタンス
 * @param id 判定するユニットのID
 * @returns 選択されているかどうか
 */
export const isSelectedUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  return editor.state.selectedUnitIds.includes(id);
};

/**
 * 選択されたユニットを取得する
 * @param editor エディタインスタンス
 * @returns 選択されたユニット、またはnull
 */
export const getSelectedUnits = (editor: Editor): UnitTreeNode[] => {
  const selectedUnitIds = getSelectedUnitIds(editor);
  return selectedUnitIds.map((id) => findUnitById(editor, id)).filter((unit): unit is UnitTreeNode => unit !== null);
};

/**
 * 次のユニットを取得する
 * @param editor エディタインスタンス
 * @returns 次のユニット、またはnull
 */
export const getNextUnit = (editor: Editor): UnitTreeNode | null => {
  const currentSelectedId = editor.state.selectedUnitIds[0];
  if (!currentSelectedId) {
    // 現在選択されていない場合は最初のユニットを返す
    const allUnits = editor.flatten(editor.state.units);
    if (allUnits.length > 0) {
      return findUnitById(editor, allUnits[0].id);
    }
    return null;
  }

  const allUnits = editor.flatten(editor.state.units);
  const currentIndex = allUnits.findIndex((unit) => unit.id === currentSelectedId);

  if (currentIndex === -1 || currentIndex === allUnits.length - 1) {
    // 現在のユニットが見つからないか、最後のユニットの場合は最初のユニットを返す
    if (allUnits.length > 0) {
      return findUnitById(editor, allUnits[0].id);
    }
    return null;
  }

  // 次のユニットを返す
  return findUnitById(editor, allUnits[currentIndex + 1].id);
};

/**
 * 前のユニットを取得する
 * @param editor エディタインスタンス
 * @returns 前のユニット、またはnull
 */
export const getPreviousUnit = (editor: Editor): UnitTreeNode | null => {
  const currentSelectedId = editor.state.selectedUnitIds[0];
  if (!currentSelectedId) {
    // 現在選択されていない場合は最後のユニットを返す
    const allUnits = editor.flatten(editor.state.units);
    if (allUnits.length > 0) {
      return findUnitById(editor, allUnits[allUnits.length - 1].id);
    }
    return null;
  }

  const allUnits = editor.flatten(editor.state.units);
  const currentIndex = allUnits.findIndex((unit) => unit.id === currentSelectedId);

  if (currentIndex === -1 || currentIndex === 0) {
    // 現在のユニットが見つからないか、最初のユニットの場合は最後のユニットを返す
    if (allUnits.length > 0) {
      return findUnitById(editor, allUnits[allUnits.length - 1].id);
    }
    return null;
  }

  // 前のユニットを返す
  return findUnitById(editor, allUnits[currentIndex - 1].id);
};

export const canInsertUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const unit = findUnitById(editor, id);
  if (!unit) {
    return false;
  }
  return validateUnitInsert(editor, unit).valid;
};

export const canDuplicateUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const unit = findUnitById(editor, id);
  if (!unit) {
    return false;
  }
  return validateUnitDuplicate(editor, unit).valid;
};

export const canMoveUnit = (editor: Editor, id: UnitTreeNode['id'], position: UnitPosition): boolean => {
  const unit = findUnitById(editor, id);
  if (!unit) {
    return false;
  }
  return validateUnitMove(editor, unit, position).valid;
};

export const canUnwrapUnit = (editor: Editor, id: UnitTreeNode['id']): boolean => {
  const unit = findUnitById(editor, id);
  if (!unit) {
    return false;
  }
  return validateUnitUnwrap(editor, unit).valid;
};

export const canWrapUnit = (editor: Editor, ids: UnitTreeNode['id'][]): boolean => {
  return validateUnitWrap(editor, ids).valid;
};

export const canAlignUnit = (editor: Editor, type: UnitTreeNode['type'], version: UnitAlignVersion): boolean => {
  const unitDef = editor.findUnitDef(type);
  if (unitDef === null) {
    return false;
  }
  if (unitDef.supports?.align === undefined) {
    return false;
  }
  const alignOptions =
    typeof unitDef.supports.align === 'function' ? unitDef.supports.align(version) : unitDef.supports.align;
  if (alignOptions.length === 0) {
    return false;
  }
  return true;
};

export const getAlignOptions = (
  editor: Editor,
  type: UnitTreeNode['type'],
  version: UnitAlignVersion
): UnitAlignOption[] => {
  const unitDef = editor.findUnitDef(type);
  if (unitDef === null) {
    return [];
  }
  if (unitDef.supports?.align === undefined) {
    return [];
  }
  return typeof unitDef.supports.align === 'function' ? unitDef.supports.align(version) : unitDef.supports.align;
};
