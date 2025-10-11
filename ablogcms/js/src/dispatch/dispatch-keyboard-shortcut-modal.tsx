import { Suspense, lazy, useState, useEffect } from 'react';
import { render } from '../utils/react';
import { registerCoreShortcuts } from '../features/keyboard-shortcut';

interface RendererProps {
  buttons: NodeListOf<HTMLButtonElement>;
}

export default function dispatchKeyboardShortcutModal(context: Document | Element = document) {
  const buttons = context.querySelectorAll<HTMLButtonElement>('.js-keyboard-shortcut-modal-open');

  if (buttons.length === 0) {
    return;
  }

  const KeyboardShortcutModal = lazy(
    () =>
      import(
        /* webpackChunkName: "keyboard-shortcut-modal" */ '../features/keyboard-shortcut/components/keyboard-shortcut-modal'
      )
  );

  const Renderer = ({ buttons }: RendererProps) => {
    const [isOpen, setIsOpen] = useState(false);

    // デフォルトのショートカットを登録
    useEffect(() => {
      registerCoreShortcuts();
    }, []);

    const handleOpen = () => {
      setIsOpen(true);
    };

    const handleClose = () => {
      setIsOpen(false);
    };

    // ボタンにイベントリスナーを追加
    useEffect(() => {
      buttons.forEach((button) => {
        button.addEventListener('click', handleOpen);
      });

      return () => {
        buttons.forEach((button) => {
          button.removeEventListener('click', handleOpen);
        });
      };
    }, [buttons]);

    return (
      <Suspense fallback={null}>
        <KeyboardShortcutModal isOpen={isOpen} onClose={handleClose} />
      </Suspense>
    );
  };

  const rootDom = document.createElement('div');
  rootDom.id = 'acms-admin-keyboard-shortcut-modal-root';
  document.body.appendChild(rootDom);

  render(<Renderer buttons={buttons} />, rootDom);
}
