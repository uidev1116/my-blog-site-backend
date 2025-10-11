import { commandList } from '../../config/command';
import type { ShortcutKey, ShortcutCategory, ShortcutRegistry } from './types';

class KeyboardShortcutRegistry {
  private registry: ShortcutRegistry = {
    categories: new Map(),
    shortcuts: new Map(),
  };

  /**
   * ショートカットカテゴリーを登録する
   * @param id カテゴリーの一意ID
   * @param category カテゴリー情報
   */
  registerCategory(id: string, category: Omit<ShortcutCategory, 'id'>): void {
    this.registry.categories.set(id, { id, ...category });
  }

  /**
   * ショートカットキーを登録する
   * @param id ショートカットの一意ID
   * @param shortcut ショートカット情報
   */
  registerShortcut(id: string, shortcut: Omit<ShortcutKey, 'id'>): void {
    this.registry.shortcuts.set(id, { id, ...shortcut });
  }

  /**
   * 登録されたカテゴリーとショートカットを取得する
   * @returns ソートされたカテゴリーとショートカットの配列
   */
  getShortcuts(): Array<{ category: ShortcutCategory; shortcuts: ShortcutKey[] }> {
    const categoryMap = new Map<string, ShortcutKey[]>();

    // ショートカットをカテゴリー別にグループ化
    this.registry.shortcuts.forEach((shortcut) => {
      const category = this.registry.categories.get(shortcut.categoryId);
      if (category) {
        if (!categoryMap.has(shortcut.categoryId)) {
          categoryMap.set(shortcut.categoryId, []);
        }
        categoryMap.get(shortcut.categoryId)!.push(shortcut);
      }
    });

    // カテゴリーとショートカットをソートして返す
    const shortcuts = Array.from(this.registry.categories.values())
      .sort((a, b) => (a.order || 0) - (b.order || 0))
      .map((category) => ({
        category,
        shortcuts: (categoryMap.get(category.id) || []).sort((a, b) => (a.order || 0) - (b.order || 0)),
      }))
      .filter(({ shortcuts }) => shortcuts.length > 0);

    return shortcuts.map(({ category, shortcuts }) => ({
      category,
      shortcuts: shortcuts.map((shortcut) => ({
        ...shortcut,
        commands: shortcut.commands.map((command) => this.normalizeCommand(command)),
      })),
    }));
  }

  /**
   * コマンド文字列からコマンド文字列を正規化する
   * @param command コマンド文字列
   * @returns コマンド文字列
   */
  private normalizeCommand(command: string): string {
    const cmd = commandList.find((cmd) => cmd.id === command);
    return cmd ? cmd.command : command;
  }

  /**
   * レジストリをクリアする
   */
  clear(): void {
    this.registry.categories.clear();
    this.registry.shortcuts.clear();
  }
}

// グローバルインスタンス
export const keyboardShortcutRegistry = new KeyboardShortcutRegistry();

// 便利な関数
export const registerKeyboardShortcutCategory = (
  ...args: Parameters<KeyboardShortcutRegistry['registerCategory']>
): void => {
  keyboardShortcutRegistry.registerCategory(...args);
};

export const registerKeyboardShortcut = (...args: Parameters<KeyboardShortcutRegistry['registerShortcut']>): void => {
  keyboardShortcutRegistry.registerShortcut(...args);
};
