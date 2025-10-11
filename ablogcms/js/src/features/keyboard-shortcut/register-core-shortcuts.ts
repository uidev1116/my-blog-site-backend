import { registerKeyboardShortcutCategory, registerKeyboardShortcut } from './registry';

// cmsコアのショートカットカテゴリーを登録
export const registerCoreShortcuts = (): void => {
  // ユニット編集のショートカット
  registerKeyboardShortcutCategory('unit-edit', {
    heading: 'ユニット編集',
    order: 1,
  });

  // // エントリー管理
  // registerKeyboardShortcutCategory('entry-admin', {
  //   heading: 'エントリー管理',
  //   order: 2,
  // });

  // ブロックエディター
  registerKeyboardShortcutCategory('block-editor', {
    heading: 'ブロックエディター',
    order: 2,
  });

  // その他
  // registerKeyboardShortcutCategory('other', {
  //   heading: 'その他',
  //   order: 4,
  // });

  // エントリー編集のショートカット
  registerKeyboardShortcut('duplicate-unit', {
    label: '選択したユニットを複製する。',
    commands: ['Shift', 'Mod', 'D'],
    categoryId: 'unit-edit',
    order: 1,
  });

  registerKeyboardShortcut('delete-unit', {
    label: '選択したユニットを削除する。',
    commands: ['Shift', 'Mod', 'Backspace'],
    categoryId: 'unit-edit',
    order: 2,
  });

  registerKeyboardShortcut('change-unit-status', {
    label: '選択したユニットのステータスを変更する。',
    commands: ['Shift', 'Mod', 'H'],
    categoryId: 'unit-edit',
    order: 3,
  });

  registerKeyboardShortcut('move-unit-up', {
    label: '選択したユニットの並び順を直前のユニットと入れ替える。',
    commands: ['Shift', 'Mod', 'ArrowUp'],
    categoryId: 'unit-edit',
    order: 4,
  });

  registerKeyboardShortcut('move-unit-down', {
    label: '選択したユニットの並び順を直後のユニットと入れ替える。',
    commands: ['Shift', 'Mod', 'ArrowDown'],
    categoryId: 'unit-edit',
    order: 5,
  });

  // // エントリー管理のショートカット（プレースホルダー）
  // registerKeyboardShortcut('placeholder-entry-management', {
  //   label: 'テキストが入ります。',
  //   commands: ['Shift', 'Mod', 'D'],
  //   categoryId: 'entry-admin',
  //   order: 1,
  // });

  registerKeyboardShortcut('block-move-up', {
    label: 'ブロックを上に移動する。',
    commands: ['Alt', 'Mod', 'ArrowUp'],
    categoryId: 'block-editor',
    order: 1,
  });

  registerKeyboardShortcut('block-move-down', {
    label: 'ブロックを下に移動する。',
    commands: ['Alt', 'Mod', 'ArrowDown'],
    categoryId: 'block-editor',
    order: 2,
  });

  registerKeyboardShortcut('block-duplicate', {
    label: 'ブロックを複製する。',
    commands: ['Shift', 'Mod', 'D'],
    categoryId: 'block-editor',
    order: 3,
  });

  registerKeyboardShortcut('block-format-clear', {
    label: '書式をクリアする。',
    commands: ['Shift', 'Mod', 'X'],
    categoryId: 'block-editor',
    order: 4,
  });

  registerKeyboardShortcut('block-delete', {
    label: 'ブロックを削除する。',
    commands: ['Shift', 'Mod', 'Backspace'],
    categoryId: 'block-editor',
    order: 5,
  });

  registerKeyboardShortcut('bold-text', {
    label: '選択したテキストを太字にする。',
    commands: ['Mod', 'B'],
    categoryId: 'block-editor',
    order: 6,
  });

  registerKeyboardShortcut('italic-text', {
    label: '選択したテキストを斜体にする。',
    commands: ['Mod', 'I'],
    categoryId: 'block-editor',
    order: 7,
  });

  registerKeyboardShortcut('underline-text', {
    label: '選択したテキストを下線にする。',
    commands: ['Mod', 'U'],
    categoryId: 'block-editor',
    order: 8,
  });

  registerKeyboardShortcut('strike-text', {
    label: '選択したテキストに打ち消し線を引く。',
    commands: ['Shift', 'Mod', 'S'],
    categoryId: 'block-editor',
    order: 9,
  });

  registerKeyboardShortcut('code-text', {
    label: '選択したテキストをコードとしてフォーマットする。',
    commands: ['Mod', 'E'],
    categoryId: 'block-editor',
    order: 10,
  });

  registerKeyboardShortcut('subscript-text', {
    label: '選択したテキストを下付き文字にする。',
    commands: ['Mod', ','],
    categoryId: 'block-editor',
    order: 11,
  });

  registerKeyboardShortcut('superscript-text', {
    label: '選択したテキストを上付き文字にする。',
    commands: ['Mod', '.'],
    categoryId: 'block-editor',
    order: 12,
  });

  // その他のショートカット（プレースホルダー）
  // registerKeyboardShortcut('placeholder-other', {
  //   label: 'テキストが入ります。',
  //   commands: ['Shift', 'Mod', 'D'],
  //   categoryId: 'other',
  //   order: 1,
  // });
};
