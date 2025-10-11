import type Editor from './editor';
import type { UnitDefInterface, UnitTreeNode } from './types/unit';

export default class UnitFactory {
  #editor: Editor;

  #unitDefs: Record<string, UnitDefInterface> = {};

  constructor(props: { editor: Editor; unitDefs: Record<string, UnitDefInterface> }) {
    this.#editor = props.editor;
    this.#unitDefs = props.unitDefs;
  }

  create(
    name: string,
    options: Partial<Pick<UnitTreeNode, 'name' | 'attributes' | 'children' | 'align' | 'group'>> = {}
  ): UnitTreeNode {
    const unitDef = this.find(name);
    if (!unitDef) {
      throw new Error(`Unit definition not found for name: ${name}`);
    }

    const id = this.#editor.generateUnitId();

    const unit: UnitTreeNode = {
      id,
      type: name,
      status: 'open',
      collapsed: false,
      children: [],
      ...options,
      attributes: options.attributes || {},
      name: options.name || unitDef.name,
    };
    return unit;
  }

  public find(name: string): UnitDefInterface | null {
    return this.#unitDefs[this.resolveName(name)] || null;
  }

  public registerUnitDefinition(name: string, unitDef: UnitDefInterface): void {
    this.#unitDefs[name] = unitDef;
  }

  public unregisterUnitDefinition(name: string): void {
    delete this.#unitDefs[name];
  }

  /**
   * The unit definitions of the editor.
   */
  public get unitDefs(): Record<string, UnitDefInterface> {
    return this.#unitDefs;
  }

  private resolveName(name: string): string {
    return name.indexOf('_') !== -1 ? name.substring(0, name.indexOf('_')) : name;
  }
}
