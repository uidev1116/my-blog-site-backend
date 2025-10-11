import type { EditorState, AnyEditorSelectors, SingleEditorSelectors, SelectorProps } from './types';

import type Editor from './editor.js';

export default class SelectorManager {
  editor: Editor;

  rawSelectors: AnyEditorSelectors;

  constructor(props: { editor: Editor; rawSelectors: AnyEditorSelectors }) {
    this.editor = props.editor;
    this.rawSelectors = props.rawSelectors;
  }

  get state(): EditorState {
    return this.editor.state;
  }

  /**
   * An object of all registered selectors.
   */
  public get selectors(): SingleEditorSelectors {
    const props: SelectorProps = {
      editor: this.editor,
    };

    const selectors = Object.fromEntries(
      Object.entries(this.rawSelectors).map(([name, selector]) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const method = (...args: any[]) => {
          return selector(...args)(props);
        };

        return [name, method];
      })
    );

    return selectors as unknown as SingleEditorSelectors;
  }

  /**
   * Register a selector
   */
  public registerSelector(name: string, selector: AnyEditorSelectors[keyof AnyEditorSelectors]): void {
    this.rawSelectors[name] = selector;
  }

  /**
   * Unregister a selector
   */
  public unregisterSelector(name: string): void {
    delete this.rawSelectors[name];
  }
}
