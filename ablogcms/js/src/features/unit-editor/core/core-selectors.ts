import * as selectors from './helpers/selectors';
import { AnyEditorSelectors, SelectorProps, UnitAlignVersion, UnitPosition, UnitTreeNode } from './types';

declare module '@features/unit-editor/core' {
  interface EditorSelectors {
    findUnitById: {
      findUnitById: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.findUnitById>;
    };
    findUnitPosition: {
      findUnitPosition: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.findUnitPosition>;
    };
    findUnitIndex: {
      findUnitIndex: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.findUnitIndex>;
    };
    findParentUnit: {
      findParentUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.findParentUnit>;
    };
    isFirstIndexUnit: {
      isFirstIndexUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.isFirstIndexUnit>;
    };
    isLastIndexUnit: {
      isLastIndexUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.isLastIndexUnit>;
    };
    isFirstPositionUnit: {
      isFirstPositionUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.isFirstPositionUnit>;
    };
    isLastPositionUnit: {
      isLastPositionUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.isLastPositionUnit>;
    };
    hasAncestorWithStatus: {
      hasAncestorWithStatus: (
        id: UnitTreeNode['id'],
        status: UnitTreeNode['status']
      ) => ReturnType<typeof selectors.hasAncestorWithStatus>;
    };
    getSelectedUnitIds: {
      getSelectedUnitIds: () => string[];
    };
    isSelectedUnit: {
      isSelectedUnit: (id: UnitTreeNode['id']) => boolean;
    };
    getSelectedUnits: {
      getSelectedUnits: () => UnitTreeNode[];
    };
    getNextUnit: {
      getNextUnit: () => UnitTreeNode | null;
    };
    getPreviousUnit: {
      getPreviousUnit: () => UnitTreeNode | null;
    };
    canInsertUnit: {
      canInsertUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.canInsertUnit>;
    };
    canDuplicateUnit: {
      canDuplicateUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.canDuplicateUnit>;
    };
    canMoveUnit: {
      canMoveUnit: (id: UnitTreeNode['id'], position: UnitPosition) => ReturnType<typeof selectors.canMoveUnit>;
    };
    canUnwrapUnit: {
      canUnwrapUnit: (id: UnitTreeNode['id']) => ReturnType<typeof selectors.canUnwrapUnit>;
    };
    canWrapUnit: {
      canWrapUnit: (ids: UnitTreeNode['id'][]) => ReturnType<typeof selectors.canWrapUnit>;
    };
    canAlignUnit: {
      canAlignUnit: (
        type: UnitTreeNode['type'],
        version: UnitAlignVersion
      ) => ReturnType<typeof selectors.canAlignUnit>;
    };
    getAlignOptions: {
      getAlignOptions: (
        type: UnitTreeNode['type'],
        version: UnitAlignVersion
      ) => ReturnType<typeof selectors.getAlignOptions>;
    };
  }
}

const coreSelectors: AnyEditorSelectors = {
  findUnitById: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.findUnitById(props.editor, id),
  findUnitPosition: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.findUnitPosition(props.editor, id),
  findUnitIndex: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.findUnitIndex(props.editor, id),
  findParentUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.findParentUnit(props.editor, id),
  isFirstIndexUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.isFirstIndexUnit(props.editor, id),
  isLastIndexUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.isLastIndexUnit(props.editor, id),
  isFirstPositionUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) =>
    selectors.isFirstPositionUnit(props.editor, id),
  isLastPositionUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) =>
    selectors.isLastPositionUnit(props.editor, id),
  hasAncestorWithStatus: (id: UnitTreeNode['id'], status: UnitTreeNode['status']) => (props: SelectorProps) =>
    selectors.hasAncestorWithStatus(props.editor, id, status),
  getSelectedUnitIds: () => (props: SelectorProps) => selectors.getSelectedUnitIds(props.editor),
  isSelectedUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.isSelectedUnit(props.editor, id),
  getSelectedUnits: () => (props: SelectorProps) => selectors.getSelectedUnits(props.editor),
  getNextUnit: () => (props: SelectorProps) => selectors.getNextUnit(props.editor),
  getPreviousUnit: () => (props: SelectorProps) => selectors.getPreviousUnit(props.editor),
  canInsertUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.canInsertUnit(props.editor, id),
  canDuplicateUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.canDuplicateUnit(props.editor, id),
  canMoveUnit: (id: UnitTreeNode['id'], position: UnitPosition) => (props: SelectorProps) =>
    selectors.canMoveUnit(props.editor, id, position),
  canUnwrapUnit: (id: UnitTreeNode['id']) => (props: SelectorProps) => selectors.canUnwrapUnit(props.editor, id),
  canWrapUnit: (ids: UnitTreeNode['id'][]) => (props: SelectorProps) => selectors.canWrapUnit(props.editor, ids),
  canAlignUnit: (type: UnitTreeNode['type'], version: UnitAlignVersion) => (props: SelectorProps) =>
    selectors.canAlignUnit(props.editor, type, version),
  getAlignOptions: (type: UnitTreeNode['type'], version: UnitAlignVersion) => (props: SelectorProps) =>
    selectors.getAlignOptions(props.editor, type, version),
};
export default coreSelectors;
