import Editor from '../editor';
import type { EditorCommands, EditorSelectors } from '../index';
import type { UnitTree, UnitDefInterface, UnitTreeNode } from './unit';

export type ValuesOf<T> = T[keyof T];

export type KeysWithTypeOf<T, Type> = { [P in keyof T]: T[P] extends Type ? P : never }[keyof T];

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type UnionToIntersection<U> = (U extends any ? (k: U) => void : never) extends (k: infer I) => void ? I : never;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type EditorCommand = (...args: any[]) => void;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type AnyEditorCommands = Record<string, (...args: any[]) => EditorCommand>;

export type UnionEditorCommands<T = EditorCommand> = UnionToIntersection<
  ValuesOf<Pick<EditorCommands<T>, KeysWithTypeOf<EditorCommands<T>, object>>>
>;

export type RawEditorCommands = {
  [Item in keyof UnionEditorCommands]: UnionEditorCommands<EditorCommand>[Item];
};

export type SingleEditorCommands = {
  [Item in keyof UnionEditorCommands]: UnionEditorCommands<void>[Item];
};

export type ChainedEditorCommands = {
  [Item in keyof UnionEditorCommands]: UnionEditorCommands<ChainedEditorCommands>[Item];
} & {
  run: () => void;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type EditorSelector = (...args: any[]) => any;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type AnyEditorSelectors = Record<string, (...args: any[]) => any>;

export type UnionEditorSelectors = UnionToIntersection<
  ValuesOf<Pick<EditorSelectors, KeysWithTypeOf<EditorSelectors, object>>>
>;

export type RawEditorSelectors = {
  [Item in keyof UnionEditorSelectors]: UnionEditorSelectors[Item];
};

export type SingleEditorSelectors = {
  [Item in keyof UnionEditorSelectors]: UnionEditorSelectors[Item];
};

export interface EditorState {
  units: UnitTree;
  selectedUnitIds: UnitTreeNode['id'][];
}

export interface CommandProps {
  editor: Editor;
}

export interface SelectorProps {
  editor: Editor;
}

export interface EditorEvents {
  beforeCreate: {
    /**
     * The editor instance
     */
    editor: Editor;
  };
  create: {
    /**
     * The editor instance
     */
    editor: Editor;
  };
  beforeTransaction: {
    /**
     * The editor instance
     */
    editor: Editor;
    /**
     * The command that is being executed
     */
    command: string | string[];
  };
  transaction: {
    /**
     * The editor instance
     */
    editor: Editor;
    /**
     * The command that is being executed
     */
    command: string | string[];
  };
  destroy: void;
  error: {
    /**
     * The editor instance
     */
    editor: Editor;
    /**
     * The error code
     */
    code: string;
    /**
     * The error message
     */
    message: string;
    /**
     * The unit that caused the error
     */
    unit: UnitTreeNode;
  };
  serverSideUnitRender: {
    /**
     * The editor instance
     */
    editor: Editor;
    /**
     * The unit that is being rendered
     */
    unit: UnitTreeNode;
    /**
     * The element that is being rendered
     */
    element: HTMLElement;
  };
  selectionChange: {
    /**
     * The editor instance
     */
    editor: Editor;
    /**
     * The currently selected unit IDs
     */
    selectedUnitIds: UnitTreeNode['id'][];
    /**
     * The previously selected unit IDs
     */
    previousSelectedUnitIds: UnitTreeNode['id'][];
  };
}

export interface EditorOptions {
  /**
   * The element or selector to bind the editor to
   * If `null` is passed, the editor will not be mounted automatically
   * If a function is passed, it will be called with the editor's root element
   */
  element: Element | null;
  /**
   * The content of the editor (HTML, JSON, or a JSON array)
   */
  units: UnitTree;

  /**
   * The definitions of units that can be added to the editor
   */
  unitDefs: Record<string, UnitDefInterface>;

  /**
   * The commands of the editor
   */
  commands: AnyEditorCommands;

  /**
   * The selectors of the editor
   */
  selectors: AnyEditorSelectors;

  /**
   * Called before the editor is constructed.
   */
  onBeforeCreate: (props: EditorEvents['beforeCreate']) => void;
  /**
   * Called after the editor is constructed.
   */
  onCreate: (props: EditorEvents['create']) => void;

  /**
   * Called after a transaction is applied to the editor.
   */
  onTransaction: (props: EditorEvents['transaction']) => void;
  /**
   * Called on selection change events.
   */
  onSelectionChange: (props: EditorEvents['selectionChange']) => void;
  /**
   * Called when the editor is destroyed.
   */
  onDestroy: (props: EditorEvents['destroy']) => void;

  /**
   * Called when an error occurs.
   */
  onError: (props: EditorEvents['error']) => void;

  /**
   * Called when a server-side unit is rendered.
   */
  onServerSideUnitRender: (props: EditorEvents['serverSideUnitRender']) => void;
}
