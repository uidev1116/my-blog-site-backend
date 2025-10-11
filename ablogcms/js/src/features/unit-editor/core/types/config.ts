import type { UnitInterface } from './unit';
import type Editor from '../editor';

/**
 * ユニット設定の属性値として使用可能な型
 */
export interface UnitConfigInterface extends Pick<UnitInterface, 'id' | 'name' | 'type' | 'align' | 'group'> {
  /**
   * 折りたたみ状態
   */
  collapsed?: boolean;

  size?: string;
  edit?: string;
  field_1?: string;
  field_2?: string;
  field_3?: string;
  field_4?: string;
  field_5?: string;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface UnitConfigListItem extends UnitConfigInterface {
  parentId: UnitConfigInterface['id'] | null;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface UnitConfigTreeNode extends UnitConfigInterface {
  children: UnitConfigTree;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type UnitConfigList = UnitConfigListItem[];

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type UnitConfigTree = UnitConfigTreeNode[];

export interface ConfigEditor {
  id: string;
  configs: UnitConfigTree;
  namePrefix: string;
  insert: (unit: UnitConfigTreeNode) => void;
  remove: (id: UnitConfigTreeNode['id']) => void;
  move: (id: UnitConfigTreeNode['id'], newIndex: number) => void;
  update: (
    id: UnitConfigTreeNode['id'],
    data: UnitConfigTreeNode | ((config: UnitConfigTreeNode) => UnitConfigTreeNode)
  ) => void;
  find: (id: UnitConfigTreeNode['id']) => UnitConfigTreeNode | null;
  create: (name: string, options?: { name?: string }) => UnitConfigTreeNode;
  flatten: (configs: UnitConfigTree) => UnitConfigList;
  nestify: (configs: UnitConfigList) => UnitConfigTree;
  editor: Editor;
}

export interface UnitConfigEditProps {
  config: UnitConfigListItem;
  editor: ConfigEditor;
}
