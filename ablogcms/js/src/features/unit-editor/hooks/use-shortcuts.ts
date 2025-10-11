import type { Editor } from '@features/unit-editor/core';
import useShortcut from '@hooks/use-shortcut';

interface UseShortcutsOptions {
  editor: Editor;
  enabled?: boolean;
}

/**
 * ユニットエディタ用のショートカットハンドラーフック
 */
export default function useShortcuts({ editor, enabled = true }: UseShortcutsOptions) {
  useShortcut({
    key: 'd',
    modKey: true,
    shiftKey: true,
    onKeyDown: () => {
      const selectedUnitIds = editor.selectors.getSelectedUnitIds();
      if (selectedUnitIds.length > 0) {
        selectedUnitIds.forEach((id) => {
          if (editor.selectors.canDuplicateUnit(id)) {
            editor.commands.duplicateUnit(id);
          }
        });
      }
    },
    eventTarget: editor.dom,
    enabled,
  });

  useShortcut({
    key: 'Backspace',
    modKey: true,
    shiftKey: true,
    onKeyDown: () => {
      const selectedUnitIds = editor.selectors.getSelectedUnitIds();
      if (selectedUnitIds.length > 0) {
        editor.commands.removeUnit(selectedUnitIds);
      }
    },
    eventTarget: editor.dom,
    enabled,
  });

  useShortcut({
    key: 'h',
    modKey: true,
    shiftKey: true,
    onKeyDown: () => {
      const selectedUnitIds = editor.selectors.getSelectedUnitIds();
      if (selectedUnitIds.length > 0) {
        selectedUnitIds.forEach((id) => {
          editor.commands.toggleUnitStatus(id);
        });
      }
    },
    eventTarget: editor.dom,
    enabled,
  });

  useShortcut({
    key: 'ArrowUp',
    modKey: true,
    shiftKey: true,
    onKeyDown: () => {
      const selectedUnitIds = editor.selectors.getSelectedUnitIds();
      if (selectedUnitIds.length > 0) {
        selectedUnitIds.forEach((id) => {
          if (!editor.selectors.isFirstIndexUnit(id)) {
            editor.commands.moveUpUnit(id);
          }
        });
      }
    },
    eventTarget: editor.dom,
    enabled,
  });

  useShortcut({
    key: 'ArrowDown',
    modKey: true,
    shiftKey: true,
    onKeyDown: () => {
      const selectedUnitIds = editor.selectors.getSelectedUnitIds();
      if (selectedUnitIds.length > 0) {
        selectedUnitIds.forEach((id) => {
          if (!editor.selectors.isLastIndexUnit(id)) {
            editor.commands.moveDownUnit(id);
          }
        });
      }
    },
    eventTarget: editor.dom,
    enabled,
  });
}
