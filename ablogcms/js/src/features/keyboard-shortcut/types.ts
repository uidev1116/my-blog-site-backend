export interface ShortcutKey {
  /** ショートカットの説明ラベル */
  label: string;
  /** コマンドIDの配列 */
  commands: string[];
  /** カテゴリーID */
  categoryId: string;
  /** ショートカットの一意ID */
  id: string;
  /** 表示順序（小さい値が先に表示される） */
  order?: number;
}

export interface ShortcutCategory {
  /** カテゴリーID */
  id: string;
  /** カテゴリーの見出し */
  heading: string;
  /** 表示順序（小さい値が先に表示される） */
  order?: number;
}

export interface ShortcutRegistry {
  categories: Map<string, ShortcutCategory>;
  shortcuts: Map<string, ShortcutKey>;
}
