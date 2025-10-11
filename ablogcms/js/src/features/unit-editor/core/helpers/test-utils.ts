import type { EditorOptions } from '../types/editor';
import Editor from '../editor';
import coreCommands from '../core-commands';
import coreSelectors from '../core-selectors';
import { UnitDefInterface, UnitStatus, UnitTreeNode } from '../types';

export function createMockUnitDef(options: Partial<UnitDefInterface> = {}): UnitDefInterface {
  const unitDef: UnitDefInterface = {
    type: 'mock',
    name: 'Mock',
    edit: () => null,
    ...options,
  };
  return unitDef;
}

/**
 * モックのエディターを作成します
 * 最小限の機能のみで初期化されます
 */
export function createMockEditor(options: Partial<EditorOptions> = {}): Editor {
  return new Editor({
    commands: coreCommands,
    selectors: coreSelectors,
    unitDefs: {
      mock: createMockUnitDef(),
    },
    ...options,
  });
}

export function createMockUnit(options: Partial<UnitTreeNode> = {}): UnitTreeNode {
  const unit: UnitTreeNode = {
    id: 'test',
    type: 'mock',
    name: 'Test Unit',
    status: 'open' as UnitStatus,
    collapsed: false,
    children: [],
    attributes: {},
    ...options,
  };
  return unit;
}
