import type Editor from '../editor';
import type { UnitTreeNode, UnitPosition, UnitDefInterface } from '../types/unit';
import { findParentUnit, findUnitById, findUnitPosition } from './selectors';

export type ValidationResult =
  | {
      valid: false;
      reason: string;
    }
  | {
      valid: true;
    };

/**
 * 指定されたタイプのユニットが存在するかを再帰的に検索する
 */
const findUnitOfType = (units: UnitTreeNode[], type: string): boolean => {
  return units.some((unit) => {
    if (unit.type === type) {
      return true;
    }
    if (unit.children) {
      return findUnitOfType(unit.children, type);
    }
    return false;
  });
};

/**
 * ユニットのネスト可否をバリデーションする
 */
export const validateUnitNesting = (editor: Editor, unit: UnitTreeNode): ValidationResult => {
  const unitDef = editor.findUnitDef(unit.type);
  if (unitDef === null) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.unit_definition_not_found') };
  }

  // nestedのチェック（デフォルトはtrue）
  const nestedAllowed = unitDef.supports?.nested !== false;

  if (!nestedAllowed) {
    return {
      valid: false,
      reason: ACMS.i18n('unit_editor.error.cannot_be_nested'),
    };
  }

  return { valid: true };
};

/**
 * ユニットのmultipleオプションをバリデーションする
 */
export const validateUnitMultiple = (editor: Editor, unit: UnitTreeNode): ValidationResult => {
  const unitDef = editor.findUnitDef(unit.type);
  if (unitDef === null) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.unit_definition_not_found') };
  }

  // multipleのチェック（デフォルトはtrue）
  const multipleAllowed = unitDef.supports?.multiple !== false;
  if (!multipleAllowed) {
    // 既存のユニットを再帰的に検索
    const hasExistingUnit = findUnitOfType(editor.state.units, unit.type);
    if (hasExistingUnit) {
      return {
        valid: false,
        reason: ACMS.i18n('unit_editor.error.only_one_instance_allowed'),
      };
    }
  }

  return { valid: true };
};

/**
 * ユニットの挿入時のバリデーションを実行する
 */
export const validateUnitInsert = (editor: Editor, unit: UnitTreeNode, position?: UnitPosition): ValidationResult => {
  if (position?.rootId !== undefined) {
    // ネストのバリデーション
    const nestingValidation = validateUnitNesting(editor, unit);
    if (!nestingValidation.valid) {
      return nestingValidation;
    }
  }

  // multipleのバリデーション
  const multipleValidation = validateUnitMultiple(editor, unit);
  if (!multipleValidation.valid) {
    return multipleValidation;
  }

  return { valid: true };
};

/**
 * ユニットのduplicateオプションをバリデーションする
 */
export const validateUnitDuplicate = (editor: Editor, unit: UnitTreeNode): ValidationResult => {
  const unitDef = editor.findUnitDef(unit.type);
  if (unitDef === null) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.unit_definition_not_found') };
  }

  function isDuplicateAllowed(unitDef: UnitDefInterface): boolean {
    if (typeof unitDef.supports?.duplicate === 'function') {
      return unitDef.supports?.duplicate(unit, editor);
    }
    if (unitDef.supports?.duplicate === false) {
      return false;
    }
    // デフォルトはtrue
    return true;
  }

  const duplicateAllowed = isDuplicateAllowed(unitDef);
  if (!duplicateAllowed) {
    return {
      valid: false,
      reason: ACMS.i18n('unit_editor.error.cannot_be_duplicated'),
    };
  }

  // 子ユニットを再帰的にチェック
  if (unit.children) {
    for (const child of unit.children) {
      const childValidation = validateUnitDuplicate(editor, child);
      if (!childValidation.valid) {
        return {
          valid: false,
          reason: ACMS.i18n('unit_editor.error.contains_non_duplicatable_unit'),
        };
      }
    }
  }

  return { valid: true };
};

/**
 * ユニットのmoveHierarchyオプションをバリデーションする
 */
export const validateUnitMoveHierarchy = (
  editor: Editor,
  unit: UnitTreeNode,
  position?: UnitPosition
): ValidationResult => {
  const unitDef = editor.findUnitDef(unit.type);
  if (unitDef === null) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.unit_definition_not_found') };
  }

  function isMoveHierarchyAllowed(unitDef: UnitDefInterface): boolean {
    if (typeof unitDef.supports?.moveHierarchy === 'function') {
      return unitDef.supports?.moveHierarchy(unit, editor);
    }
    if (unitDef.supports?.moveHierarchy === false) {
      return false;
    }
    // デフォルトはtrue
    return true;
  }

  const moveHierarchyAllowed = isMoveHierarchyAllowed(unitDef);
  if (!moveHierarchyAllowed) {
    // positionが指定されている場合は階層移動のチェックを行う
    if (position) {
      if (findParentUnit(editor, unit.id)?.id !== position.rootId) {
        return {
          valid: false,
          reason: ACMS.i18n('unit_editor.error.cannot_move_hierarchy'),
        };
      }
    } else {
      return {
        valid: false,
        reason: ACMS.i18n('unit_editor.error.cannot_move_hierarchy_without_position'),
      };
    }
  }

  // 子ユニットを再帰的にチェック
  if (unit.children) {
    for (const child of unit.children) {
      const childValidation = validateUnitMoveHierarchy(editor, child, position);
      if (!childValidation.valid) {
        return {
          valid: false,
          reason: ACMS.i18n('unit_editor.error.contains_non_movable_unit'),
        };
      }
    }
  }

  return { valid: true };
};

/**
 * ユニット移動時のバリデーションを実行する
 */
export const validateUnitMove = (editor: Editor, unit: UnitTreeNode, position: UnitPosition): ValidationResult => {
  if (position.rootId !== undefined) {
    // ネストのバリデーション
    const nestingValidation = validateUnitNesting(editor, unit);
    if (!nestingValidation.valid) {
      return nestingValidation;
    }
  }

  // 階層移動のバリデーション
  const moveHierarchyValidation = validateUnitMoveHierarchy(editor, unit, position);
  if (!moveHierarchyValidation.valid) {
    return moveHierarchyValidation;
  }

  return { valid: true };
};

/**
 * ユニットラップ時のバリデーションを実行する
 */
export const validateUnitWrap = (editor: Editor, ids: UnitTreeNode['id'][]): ValidationResult => {
  // ユニットが存在するかチェック
  const positions = ids
    .map((id) => findUnitPosition(editor, id))
    .filter((p): p is UnitPosition => p !== null)
    .sort((a, b) => a.index - b.index);

  if (positions.length !== ids.length) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.cannot_be_wrapped_with_unknown_unit') };
  }

  // 全てのユニットが同じ親ユニットに属しているかチェック
  const parentRootId = positions[0].rootId;
  if (!positions.every((p) => p.rootId === parentRootId)) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.cannot_be_wrapped_with_same_parent') };
  }

  // 全てのユニットが連続しているかチェック
  const isConsecutive = positions.every((p, idx) => (idx === 0 ? true : p.index === positions[idx - 1].index + 1));
  if (!isConsecutive) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.cannot_be_wrapped_with_non_consecutive_units') };
  }

  for (const id of ids) {
    const unit = findUnitById(editor, id);
    if (unit === null) {
      return { valid: false, reason: ACMS.i18n('unit_editor.error.cannot_be_wrapped_with_unknown_unit') };
    }

    const nestingValidation = validateUnitNesting(editor, unit);
    if (!nestingValidation.valid) {
      return nestingValidation;
    }

    const moveHierarchyValidation = validateUnitMoveHierarchy(editor, unit);
    if (!moveHierarchyValidation.valid) {
      return moveHierarchyValidation;
    }
  }

  return { valid: true };
};

export const validateUnitUnwrap = (editor: Editor, unit: UnitTreeNode): ValidationResult => {
  if (unit.children.length === 0) {
    return { valid: false, reason: ACMS.i18n('unit_editor.error.cannot_be_unwraped_with_empty_unit') };
  }

  // 階層移動のバリデーション
  const moveHierarchyValidation = validateUnitMoveHierarchy(editor, unit);
  if (!moveHierarchyValidation.valid) {
    return moveHierarchyValidation;
  }

  return { valid: true };
};
