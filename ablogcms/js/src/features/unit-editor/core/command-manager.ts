import type {
  EditorState,
  AnyEditorCommands,
  CommandProps,
  SingleEditorCommands,
  ChainedEditorCommands,
  EditorCommand,
} from './types';

import type Editor from './editor.js';

export default class CommandManager {
  editor: Editor;

  rawCommands: AnyEditorCommands;

  constructor(props: { editor: Editor; rawCommands: AnyEditorCommands }) {
    this.editor = props.editor;
    this.rawCommands = props.rawCommands;
  }

  get state(): EditorState {
    return this.editor.state;
  }

  /**
   * An object of all registered commands.
   */
  public get commands(): SingleEditorCommands {
    const props: CommandProps = {
      editor: this.editor,
    };

    const commands = Object.fromEntries(
      Object.entries(this.rawCommands).map(([name, command]) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const method = (...args: any[]) => {
          this.editor.emit('beforeTransaction', { editor: this.editor, command: name });
          const result = command(...args)(props);
          this.editor.emit('transaction', { editor: this.editor, command: name });
          return result;
        };

        return [name, method];
      })
    );

    return commands as unknown as SingleEditorCommands;
  }

  public get chain(): () => ChainedEditorCommands {
    return () => this.createChain();
  }

  private createChain(): ChainedEditorCommands {
    const functions: EditorCommand[] = [];

    const run = () => {
      this.editor.emit('beforeTransaction', { editor: this.editor, command: functions.map((func) => func.name) });
      functions.forEach((func) => {
        const props: CommandProps = {
          editor: this.editor,
        };
        func(props);
      });
      this.editor.emit('transaction', { editor: this.editor, command: functions.map((func) => func.name) });
    };

    const chain = {
      ...Object.fromEntries(
        Object.entries(this.rawCommands).map(([name, command]) => {
          const chainedCommand = (...args: never[]) => {
            const func = command(...args);
            functions.push(func);

            return chain;
          };

          return [name, chainedCommand];
        })
      ),
      run,
    } as unknown as ChainedEditorCommands;

    return chain;
  }

  /**
   * Register a command
   */
  public registerCommand(name: string, command: AnyEditorCommands[keyof AnyEditorCommands]): void {
    this.rawCommands[name] = command;
  }

  /**
   * Unregister a command
   */
  public unregisterCommand(name: string): void {
    delete this.rawCommands[name];
  }
}
