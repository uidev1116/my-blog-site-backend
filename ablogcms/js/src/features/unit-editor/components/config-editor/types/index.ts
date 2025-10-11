import { UnitEditorSettings } from '@features/unit-editor/types';
import { UnitMenuItem as BaseUnitMenuItem } from '@features/unit-editor/core/types';

export type UnitMenuItem = Pick<BaseUnitMenuItem, 'id' | 'label'>;

// ユニットタイプの定義
export interface UnitTypeDefinition {
  type: string;
  label: string;
  baseType: string; // 特定指定子を除外した一般名
}

// タグオプション（テキストユニット用）
export interface TagOption {
  value: string;
  label: string;
}

// フロント側に渡す JSON の構成
export interface UnitConfigEditorSettings extends Omit<UnitEditorSettings, 'unitDefs'> {
  // ユニットタイプの定義
  unitDefs: UnitMenuItem[];
  // テキストユニットのタグオプション
  textTagOptions: TagOption[];
}
