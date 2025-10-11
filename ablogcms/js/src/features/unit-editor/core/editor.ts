import { v4 as uuidv4 } from 'uuid';
import { EventEmitter } from './event-emitter';
import type { UnitTreeNode, UnitDefInterface, UnitTree, UnitList } from './types/unit';
import type {
  EditorEvents,
  EditorOptions,
  EditorState,
  SingleEditorCommands,
  SingleEditorSelectors,
  ChainedEditorCommands,
} from './types/editor';

import CommandManager from './command-manager';
import SelectorManager from './selector-manager';
import UnitFactory from './factory';
import { flatten, nestify } from './utlls';
import { arraysEqual } from '../../../utils/array';

class Editor extends EventEmitter<EditorEvents> {
  /**
   * The state of the editor
   */
  private _state: EditorState;

  /**
   * The DOM element of the editor
   */
  private _dom!: HTMLElement;

  /**
   * The command manager of the editor
   */
  private commandManager: CommandManager;

  /**
   * The selectors of the editor
   */
  private selectorManager: SelectorManager;

  /**
   * The unit factory of the editor
   */
  private unitFactory: UnitFactory;

  /**
   * The options of the editor
   */
  private options: EditorOptions = {
    element: typeof document !== 'undefined' ? document.createElement('div') : null,
    units: [],
    unitDefs: {},
    commands: {},
    selectors: {},
    onBeforeCreate: () => null,
    onCreate: () => null,
    onTransaction: () => null,
    onSelectionChange: () => null,
    onDestroy: () => null,
    onError: () => null,
    onServerSideUnitRender: () => null,
  };

  /**
   * Create a new editor instance.
   * @param options - The options for the editor.
   */
  constructor(options: Partial<EditorOptions>) {
    super();
    this.setOptions(options);

    this._state = {
      units: this.options.units,
      selectedUnitIds: [], // 追加: 空配列で初期化
    };

    this.unitFactory = new UnitFactory({
      editor: this,
      unitDefs: this.options.unitDefs,
    });

    this.commandManager = new CommandManager({
      editor: this,
      rawCommands: this.options.commands,
    });

    this.selectorManager = new SelectorManager({
      editor: this,
      rawSelectors: this.options.selectors,
    });

    this.on('beforeCreate', this.options.onBeforeCreate);
    this.emit('beforeCreate', { editor: this });
    this.on('create', this.options.onCreate);
    this.on('transaction', this.options.onTransaction);
    this.on('selectionChange', this.options.onSelectionChange);
    this.on('destroy', this.options.onDestroy);
    this.on('error', this.options.onError);
    this.on('serverSideUnitRender', this.options.onServerSideUnitRender);
    setTimeout(() => {
      this.emit('create', { editor: this });
    }, 0);
  }

  /**
   * set options
   */
  public setOptions(options: Partial<EditorOptions>): void {
    this.options = {
      ...this.options,
      ...options,
    };
    if (this.options.element !== null) {
      this._dom = this.options.element as HTMLElement;
    }
  }

  /**
   * get options
   */
  public getOptions(): EditorOptions {
    return this.options;
  }

  /**
   * Destroy the editor.
   */
  public destroy(): void {
    this.emit('destroy');

    this.removeAllListeners();
  }

  /**
   * An object of all registered commands.
   */
  public get commands(): SingleEditorCommands {
    return this.commandManager.commands;
  }

  /**
   * Create a command chain to call multiple commands at once.
   */
  public chain(): ChainedEditorCommands {
    return this.commandManager.chain();
  }

  /**
   * An object of all registered selectors.
   */
  public get selectors(): SingleEditorSelectors {
    return this.selectorManager.selectors;
  }

  /**
   * Get the current state of the editor.
   */
  public get state(): EditorState {
    return this._state;
  }

  /**
   * Set the current state of the editor.
   */
  public set state(state: EditorState) {
    const prevState = { ...this._state };
    this._state = state;
    if (!arraysEqual(prevState.selectedUnitIds, state.selectedUnitIds)) {
      this.emit('selectionChange', {
        editor: this,
        selectedUnitIds: state.selectedUnitIds,
        previousSelectedUnitIds: prevState.selectedUnitIds,
      });
    }
  }

  /**
   * Get the current state of the editor.
   */
  public get dom(): HTMLElement {
    return this._dom;
  }

  /**
   * The unit definitions of the editor.
   */
  public get unitDefs(): Record<string, UnitDefInterface> {
    return this.unitFactory.unitDefs;
  }

  /**
   * Register a unit definition.
   */
  public registerUnitDefinition(...args: Parameters<UnitFactory['registerUnitDefinition']>): void {
    this.unitFactory.registerUnitDefinition(...args);
  }

  /**
   * Unregister a unit definition.
   */
  public unregisterUnitDefinition(...args: Parameters<UnitFactory['unregisterUnitDefinition']>): void {
    this.unitFactory.unregisterUnitDefinition(...args);
  }

  /**
   * Find a unit definition.
   */
  public findUnitDef(...args: Parameters<UnitFactory['find']>): UnitDefInterface | null {
    return this.unitFactory.find(...args);
  }

  /**
   * Create a new unit.
   */
  public createUnit(...args: Parameters<UnitFactory['create']>): UnitTreeNode {
    return this.unitFactory.create(...args);
  }

  /**
   * Register a command.
   */
  public registerCommand(...args: Parameters<CommandManager['registerCommand']>): void {
    this.commandManager.registerCommand(...args);
  }

  /**
   * Unregister a command.
   */
  public unregisterCommand(...args: Parameters<CommandManager['unregisterCommand']>): void {
    this.commandManager.unregisterCommand(...args);
  }

  /**
   * Register a selector.
   */
  public registerSelector(...args: Parameters<SelectorManager['registerSelector']>): void {
    this.selectorManager.registerSelector(...args);
  }

  /**
   * Unregister a selector.
   */
  public unregisterSelector(...args: Parameters<SelectorManager['unregisterSelector']>): void {
    this.selectorManager.unregisterSelector(...args);
  }

  /**
   * generate a new unit id.
   */
  public generateUnitId(): string {
    return uuidv4();
  }

  /**
   * flatten the units
   */
  public flatten(tree: UnitTree): UnitList {
    return flatten(tree);
  }

  /**
   * nestify the units
   */
  public nestify(list: UnitList): UnitTree {
    return nestify(list);
  }
}

export default Editor;
