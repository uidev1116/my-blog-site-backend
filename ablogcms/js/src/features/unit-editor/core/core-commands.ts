import * as commands from './helpers/commands';
import { AnyEditorCommands, CommandProps, UnitLastPosition, UnitPosition, UnitTree, UnitTreeNode } from './types';

declare module '@features/unit-editor/core' {
  interface EditorCommands<ReturnType> {
    setUnits: {
      setUnits: (units: UnitTree) => ReturnType;
    };
    insertUnit: {
      insertUnit: (newUnit: UnitTreeNode | UnitTree, position?: UnitPosition | UnitLastPosition) => ReturnType;
    };
    insertAfterUnit: {
      insertAfterUnit: (id: UnitTreeNode['id'], newUnit: UnitTreeNode | UnitTree) => ReturnType;
    };
    insertBeforeUnit: {
      insertBeforeUnit: (id: UnitTreeNode['id'], newUnit: UnitTreeNode | UnitTree) => ReturnType;
    };
    removeUnit: {
      removeUnit: (id: UnitTreeNode['id'] | UnitTreeNode['id'][]) => ReturnType;
    };
    updateUnit: {
      updateUnit: (id: UnitTreeNode['id'], data: Partial<UnitTreeNode>) => ReturnType;
    };
    moveUnitToPosition: {
      moveUnitToPosition: (id: UnitTreeNode['id'], newPosition: UnitPosition) => ReturnType;
    };
    moveUpUnit: {
      moveUpUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
    moveDownUnit: {
      moveDownUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
    setUnitStatus: {
      setUnitStatus: (id: UnitTreeNode['id'], status: UnitTreeNode['status']) => ReturnType;
      toggleUnitStatus: (id: UnitTreeNode['id']) => ReturnType;
    };
    setUnitAlign: {
      setUnitAlign: (id: UnitTreeNode['id'], align: NonNullable<UnitTreeNode['align']>) => ReturnType;
    };
    setUnitGroup: {
      setUnitGroup: (id: UnitTreeNode['id'], group: NonNullable<UnitTreeNode['group']>) => ReturnType;
    };
    setUnitAnker: {
      setUnitAnker: (id: UnitTreeNode['id'], anker: NonNullable<UnitTreeNode['anker']>) => ReturnType;
    };
    collapsed: {
      setUnitCollapsed: (id: UnitTreeNode['id'], collapsed: UnitTreeNode['collapsed']) => ReturnType;
      toggleUnitCollapsed: (id: UnitTreeNode['id']) => ReturnType;
    };
    setUnitAttributes: {
      setUnitAttributes: (id: UnitTreeNode['id'], data: Partial<UnitTreeNode['attributes']>) => ReturnType;
    };
    duplicateUnit: {
      duplicateUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
    selectUnit: {
      selectUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
    deselectUnit: {
      deselectUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
    deselectAll: {
      deselectAll: () => ReturnType;
    };
    selectNextUnit: {
      selectNextUnit: () => ReturnType;
    };
    selectPreviousUnit: {
      selectPreviousUnit: () => ReturnType;
    };
    wrapUnits: {
      wrapUnits: (unit: UnitTreeNode, ids: UnitTreeNode['id'][]) => ReturnType;
    };
    unwrapUnit: {
      unwrapUnit: (id: UnitTreeNode['id']) => ReturnType;
    };
  }
}

const coreCommands: AnyEditorCommands = {
  setUnits: (units: UnitTree) => (props: CommandProps) => {
    props.editor.state = {
      ...props.editor.state,
      units,
    };
  },
  insertUnit: (newUnit: UnitTreeNode | UnitTree, position?: UnitPosition) => (props: CommandProps) => {
    const newUnits: UnitTree = Array.isArray(newUnit) ? newUnit : [newUnit];
    const newState = commands.insertUnit(props.editor, newUnits, position);
    props.editor.state = newState;
  },
  insertAfterUnit: (id: UnitTreeNode['id'], newUnit: UnitTreeNode | UnitTree) => (props: CommandProps) => {
    const newUnits: UnitTree = Array.isArray(newUnit) ? newUnit : [newUnit];
    const newState = commands.insertAfterUnit(props.editor, id, newUnits);
    props.editor.state = newState;
  },
  insertBeforeUnit: (id: UnitTreeNode['id'], newUnit: UnitTreeNode | UnitTree) => (props: CommandProps) => {
    const newUnits: UnitTree = Array.isArray(newUnit) ? newUnit : [newUnit];
    const newState = commands.insertBeforeUnit(props.editor, id, newUnits);
    props.editor.state = newState;
  },
  removeUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.removeUnit(props.editor, id);
    props.editor.state = newState;
  },
  updateUnit: (id: UnitTreeNode['id'], data: Partial<UnitTreeNode>) => (props: CommandProps) => {
    const newState = commands.updateUnit(props.editor, id, data);
    props.editor.state = newState;
  },
  moveUnitToPosition: (id: UnitTreeNode['id'], newPosition: UnitPosition) => (props: CommandProps) => {
    const newState = commands.moveUnitToPosition(props.editor, id, newPosition);
    props.editor.state = newState;
  },
  moveUpUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.moveUpUnit(props.editor, id);
    props.editor.state = newState;
  },
  moveDownUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.moveDownUnit(props.editor, id);
    props.editor.state = newState;
  },
  setUnitStatus: (id: UnitTreeNode['id'], status: UnitTreeNode['status']) => (props: CommandProps) => {
    const newState = commands.setUnitStatus(props.editor, id, status);
    props.editor.state = newState;
  },
  toggleUnitStatus: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.toggleUnitStatus(props.editor, id);
    props.editor.state = newState;
  },
  setUnitAlign: (id: UnitTreeNode['id'], align: NonNullable<UnitTreeNode['align']>) => (props: CommandProps) => {
    const newState = commands.setUnitAlign(props.editor, id, align);
    props.editor.state = newState;
  },
  setUnitGroup: (id: UnitTreeNode['id'], group: NonNullable<UnitTreeNode['group']>) => (props: CommandProps) => {
    const newState = commands.setUnitGroup(props.editor, id, group);
    props.editor.state = newState;
  },
  setUnitCollapsed: (id: UnitTreeNode['id'], collapsed: UnitTreeNode['collapsed']) => (props: CommandProps) => {
    const newState = commands.setUnitCollapsed(props.editor, id, collapsed);
    props.editor.state = newState;
  },
  toggleUnitCollapsed: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.toggleUnitCollapsed(props.editor, id);
    props.editor.state = newState;
  },
  setUnitAnker: (id: UnitTreeNode['id'], anker: NonNullable<UnitTreeNode['anker']>) => (props: CommandProps) => {
    const newState = commands.setUnitAnker(props.editor, id, anker);
    props.editor.state = newState;
  },
  setUnitAttributes:
    (id: UnitTreeNode['id'], data: Partial<Exclude<UnitTreeNode['attributes'], string>>) => (props: CommandProps) => {
      const newState = commands.setUnitAttributes(props.editor, id, data);
      props.editor.state = newState;
    },
  duplicateUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.duplicateUnit(props.editor, id);
    props.editor.state = newState;
  },
  selectUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.selectUnit(props.editor, id);
    props.editor.state = newState;
  },
  deselectUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.deselectUnit(props.editor, id);
    props.editor.state = newState;
  },
  deselectAll: () => (props: CommandProps) => {
    const newState = commands.deselectAll(props.editor);
    props.editor.state = newState;
  },
  selectNextUnit: () => (props: CommandProps) => {
    const newState = commands.selectNextUnit(props.editor);
    props.editor.state = newState;
  },
  selectPreviousUnit: () => (props: CommandProps) => {
    const newState = commands.selectPreviousUnit(props.editor);
    props.editor.state = newState;
  },
  wrapUnits: (unit: UnitTreeNode, ids: UnitTreeNode['id'][]) => (props: CommandProps) => {
    const newState = commands.wrapUnits(props.editor, unit, ids);
    props.editor.state = newState;
  },
  unwrapUnit: (id: UnitTreeNode['id']) => (props: CommandProps) => {
    const newState = commands.unwrapUnit(props.editor, id);
    props.editor.state = newState;
  },
};

export default coreCommands;
