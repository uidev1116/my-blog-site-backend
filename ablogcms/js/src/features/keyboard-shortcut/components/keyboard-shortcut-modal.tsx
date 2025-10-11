import { forwardRef, useCallback, useState } from 'react';
import classnames from 'classnames';
import Modal from '../../../components/modal/modal';
import { keyboardShortcutRegistry } from '../registry';
import type { ShortcutCategory, ShortcutKey as ShortcutKeyType } from '../types';

type KeyboardShortcutModalProps = Omit<React.ComponentProps<typeof Modal>, 'children'>;

interface ShortcutKeyProps {
  /** 表示するコマンド文字列 */
  command: string;
}

/**
 * 個別のキーボードショートカットキーを表示するコンポーネント
 */
const ShortcutKey = ({ command }: ShortcutKeyProps) => <kbd className="acms-admin-shortcut-key">{command}</kbd>;

interface ShortcutKeyGroupProps {
  /** 表示するコマンド文字列の配列 */
  commands: ShortcutKeyType['commands'];
}

/**
 * キーボードショートカットキーのグループを表示するコンポーネント
 */
const ShortcutKeyGroup = ({ commands }: ShortcutKeyGroupProps) => (
  <kbd className="acms-admin-shortcut-key-group">
    {commands.map((command) => (
      <ShortcutKey key={command} command={command} />
    ))}
  </kbd>
);

/**
 * 個別のショートカット項目を表示するコンポーネント
 */
const ShortcutItem = ({ label, commands }: ShortcutKeyType) => (
  <li className="acms-admin-shortcut-listitem">
    <span className="acms-admin-shortcut-label">{label}</span>
    <ShortcutKeyGroup commands={commands} />
  </li>
);

interface ShortcutSectionProps {
  category: ShortcutCategory;
  shortcuts: ShortcutKeyType[];
}

/**
 * ショートカットのセクションを表示するコンポーネント
 */
const ShortcutSection = ({ category, shortcuts }: ShortcutSectionProps) => (
  <section className="acms-admin-shortcut">
    <h4 className="acms-admin-shortcut-heading">{category.heading}</h4>
    <ul className="acms-admin-shortcut-list">
      {shortcuts.map((shortcut) => (
        <ShortcutItem key={`${category.id}-${shortcut.id}`} {...shortcut} />
      ))}
    </ul>
  </section>
);

/**
 * キーボードショートカット表示用のモーダルコンポーネント
 *
 * command.tsのコマンドリストを活用して、プラットフォームに応じた
 * 適切なキーボードショートカットを表示します。
 *
 * グローバルレジストリからショートカットを取得します。
 */
const KeyboardShortcutModal = forwardRef<HTMLDivElement, KeyboardShortcutModalProps>(
  ({ className, ...modalProps }, ref) => {
    // ショートカットデータを取得
    const [shortcuts, setShortcuts] = useState<ReturnType<typeof keyboardShortcutRegistry.getShortcuts>>(
      keyboardShortcutRegistry.getShortcuts()
    );

    const handleAfterOpen = useCallback(() => {
      if (modalProps.onAfterOpen) {
        modalProps.onAfterOpen();
      }
      setShortcuts(keyboardShortcutRegistry.getShortcuts());
    }, [modalProps]);

    return (
      <Modal
        ref={ref}
        className={classnames('acms-admin-keyboard-shortcut-modal', className)}
        aria-labelledby="acms-keyboard-shortcut-modal-title"
        {...modalProps}
        onAfterOpen={handleAfterOpen}
        isScrollable
        isCentered
      >
        <Modal.Header>キーボードショートカット</Modal.Header>
        <Modal.Body>
          {shortcuts.map(({ category, shortcuts }) => (
            <ShortcutSection key={category.id} category={category} shortcuts={shortcuts} />
          ))}
        </Modal.Body>
      </Modal>
    );
  }
);

KeyboardShortcutModal.displayName = 'KeyboardShortcutModal';

export default KeyboardShortcutModal;
