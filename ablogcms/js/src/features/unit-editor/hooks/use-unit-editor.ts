import { useRef, useState, useSyncExternalStore } from 'react';
import { Editor, type EditorState, type EditorOptions } from '@features/unit-editor/core';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UseEditorOptions extends Partial<EditorOptions> {}

class EditorInstanceManager {
  private editor: Editor | null = null;

  private subscriptions = new Set<() => void>();

  private options: React.MutableRefObject<UseEditorOptions>;

  constructor(options: React.MutableRefObject<UseEditorOptions>) {
    this.options = options;
    this.setEditor(this.createEditor());

    this.getEditor = this.getEditor.bind(this);
    this.getEditorState = this.getEditorState.bind(this);
    this.getServerSnapshot = this.getServerSnapshot.bind(this);
    this.subscribe = this.subscribe.bind(this);
    this.createEditor = this.createEditor.bind(this);
  }

  private setEditor(editor: Editor | null) {
    this.editor = editor;
    this.editor?.on('transaction', () => {
      this.subscriptions.forEach((callback) => callback());
    });
  }

  private createEditor(): Editor {
    const options: Partial<EditorOptions> = {
      ...this.options.current,
      // Always call the most recent version of the callback function by default
      onBeforeCreate: (...args) => this.options.current.onBeforeCreate?.(...args),
      onCreate: (...args) => this.options.current.onCreate?.(...args),
      onDestroy: (...args) => this.options.current.onDestroy?.(...args),
      onSelectionChange: (...args) => this.options.current.onSelectionChange?.(...args),
      onTransaction: (...args) => this.options.current.onTransaction?.(...args),
      onError: (...args) => this.options.current.onError?.(...args),
    };
    const editor = new Editor(options);

    return editor;
  }

  public getEditor(): Editor | null {
    return this.editor;
  }

  public getEditorState(): EditorState | null {
    return this.editor?.state ?? null;
  }

  // For server-side rendering, return initial units
  public getServerSnapshot(): null {
    return null;
  }

  public subscribe(onStoreChange: () => void) {
    this.subscriptions.add(onStoreChange);
    return () => {
      this.subscriptions.delete(onStoreChange);
    };
  }

  public destroy() {
    if (this.editor) {
      this.editor.destroy();
      this.editor = null;
    }
    this.subscriptions.clear();
  }
}

const useUnitEditor = (options: UseEditorOptions = {}) => {
  const mostRecentOptions = useRef(options);

  mostRecentOptions.current = options;
  const [instanceManager] = useState(() => new EditorInstanceManager(mostRecentOptions));

  useSyncExternalStore(
    instanceManager.subscribe,
    () => instanceManager.getEditorState(),
    () => instanceManager.getServerSnapshot()
  );

  const editor = instanceManager.getEditor();

  window.ACMS.unitEditor = editor;

  return editor;
};

export default useUnitEditor;
