import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { createRef } from 'react';

import BaseModal from './base-modal';

// 外部 DOM 操作系フックは jsdom と相性が悪く、また BaseModal の責務外であるため
// それぞれ stub に差し替えて、振る舞いの検証に集中する
vi.mock('../../hooks/use-focus-trap', () => ({
  default: () => ({
    isActive: false,
    isPaused: false,
    setRef: vi.fn(),
    activate: vi.fn(),
    deactivate: vi.fn(),
    pause: vi.fn(),
    unpause: vi.fn(),
  }),
}));

vi.mock('../../hooks/use-body-scroll-lock', () => ({
  default: () => ({
    setRef: vi.fn(),
    disableScroll: vi.fn(),
    enableScroll: vi.fn(),
  }),
}));

vi.mock('../../hooks/use-aria-hidden', () => ({
  default: () => ({
    setRef: vi.fn(),
    setParentRef: vi.fn(),
    hide: vi.fn(),
    unhide: vi.fn(),
  }),
}));

describe('BaseModal', () => {
  describe('レンダリング', () => {
    it('isOpen が false のときは何もレンダリングされない', () => {
      render(
        <BaseModal isOpen={false} onClose={() => {}}>
          <p>content</p>
        </BaseModal>
      );

      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(screen.queryByText('content')).not.toBeInTheDocument();
    });

    it('isOpen が true のとき children と dialog がレンダリングされる', () => {
      render(
        <BaseModal isOpen onClose={() => {}}>
          <p>content</p>
        </BaseModal>
      );

      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('content')).toBeInTheDocument();
    });

    it('既定では document.body 配下に portal される', () => {
      render(
        <BaseModal isOpen onClose={() => {}}>
          <p>content</p>
        </BaseModal>
      );

      const dialog = screen.getByRole('dialog');
      expect(document.body.contains(dialog)).toBe(true);
    });

    it('container を指定するとその要素配下に portal される', () => {
      const container = document.createElement('div');
      container.id = 'custom-container';
      document.body.appendChild(container);

      try {
        render(
          <BaseModal isOpen onClose={() => {}} container={container}>
            <p>content</p>
          </BaseModal>
        );

        const dialog = screen.getByRole('dialog');
        expect(container.contains(dialog)).toBe(true);
      } finally {
        document.body.removeChild(container);
      }
    });

    it('id / className / style がモーダルルートに反映される', () => {
      render(
        <BaseModal isOpen onClose={() => {}} id="my-modal" className="custom-class" style={{ zIndex: 1000 }}>
          <p>content</p>
        </BaseModal>
      );

      const root = document.getElementById('my-modal');
      expect(root).not.toBeNull();
      expect(root).toHaveClass('custom-class');
      expect(root).toHaveStyle({ zIndex: '1000' });
    });

    it('backdropClassName / backdropStyle がバックドロップに反映される', () => {
      render(
        <BaseModal
          isOpen
          onClose={() => {}}
          backdropClassName="custom-backdrop"
          backdropStyle={{ background: 'rgb(0, 0, 0)' }}
        >
          <p>content</p>
        </BaseModal>
      );

      const backdrop = document.querySelector<HTMLElement>('.custom-backdrop');
      expect(backdrop).not.toBeNull();
      expect(backdrop).toHaveAttribute('role', 'presentation');
      expect(backdrop).toHaveStyle({ background: 'rgb(0, 0, 0)' });
    });

    it('dialogClassName / dialogStyle が dialog に反映される', () => {
      render(
        <BaseModal
          isOpen
          onClose={() => {}}
          dialogClassName="custom-dialog"
          dialogStyle={{ background: 'rgb(255, 255, 255)' }}
        >
          <p>content</p>
        </BaseModal>
      );

      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveClass('custom-dialog');
      expect(dialog).toHaveStyle({ background: 'rgb(255, 255, 255)' });
    });

    it('ARIA 属性が dialog に反映される', () => {
      render(
        <BaseModal isOpen onClose={() => {}} role="alertdialog" aria-labelledby="title-id" aria-describedby="desc-id">
          <p>content</p>
        </BaseModal>
      );

      const dialog = screen.getByRole('alertdialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
      expect(dialog).toHaveAttribute('aria-labelledby', 'title-id');
      expect(dialog).toHaveAttribute('aria-describedby', 'desc-id');
    });

    it('既定では role=dialog / aria-modal=true', () => {
      render(
        <BaseModal isOpen onClose={() => {}}>
          <p>content</p>
        </BaseModal>
      );

      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
    });

    it('forwardRef でモーダルルート要素を取得できる', () => {
      const ref = createRef<HTMLDivElement>();

      render(
        <BaseModal isOpen onClose={() => {}} id="ref-modal" ref={ref}>
          <p>content</p>
        </BaseModal>
      );

      expect(ref.current).not.toBeNull();
      expect(ref.current?.id).toBe('ref-modal');
    });
  });

  describe('バックドロップクリックでの閉じる動作', () => {
    it('バックドロップ自身をクリックすると onClose が呼ばれる', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose} backdropClassName="bd">
          <p>content</p>
        </BaseModal>
      );

      const backdrop = document.querySelector<HTMLElement>('.bd');
      expect(backdrop).not.toBeNull();
      fireEvent.click(backdrop as HTMLElement);

      expect(handleClose).toHaveBeenCalledTimes(1);
    });

    it('dialog 内（children）をクリックしても onClose は呼ばれない', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose}>
          <p>content</p>
        </BaseModal>
      );

      fireEvent.click(screen.getByText('content'));

      expect(handleClose).not.toHaveBeenCalled();
    });

    it('dialog 自身をクリックしても onClose は呼ばれない', () => {
      // dialog 余白部分のクリックはスタイル側 (pointer-events: none) で
      // backdrop に透過させる方針。JS のロジック上は dialog がクリック対象
      // になっても閉じない仕様
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose}>
          <p>content</p>
        </BaseModal>
      );

      fireEvent.click(screen.getByRole('dialog'));

      expect(handleClose).not.toHaveBeenCalled();
    });

    it('shouldCloseOnBackdropClick が false のときはバックドロップクリックでも onClose は呼ばれない', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose} shouldCloseOnBackdropClick={false} backdropClassName="bd">
          <p>content</p>
        </BaseModal>
      );

      const backdrop = document.querySelector<HTMLElement>('.bd');
      fireEvent.click(backdrop as HTMLElement);

      expect(handleClose).not.toHaveBeenCalled();
    });
  });

  describe('Escape キーでの閉じる動作', () => {
    it('Escape を押すと onClose が呼ばれる', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose}>
          <p>content</p>
        </BaseModal>
      );

      fireEvent.keyDown(screen.getByRole('dialog'), { code: 'Escape' });

      expect(handleClose).toHaveBeenCalledTimes(1);
    });

    it('shouldCloseOnEsc が false のときは Escape を押しても onClose は呼ばれない', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose} shouldCloseOnEsc={false}>
          <p>content</p>
        </BaseModal>
      );

      fireEvent.keyDown(screen.getByRole('dialog'), { code: 'Escape' });

      expect(handleClose).not.toHaveBeenCalled();
    });

    it('Escape は stopPropagation でモーダル外に伝播しない', () => {
      const outerHandler = vi.fn();
      document.addEventListener('keydown', outerHandler);

      try {
        render(
          <BaseModal isOpen onClose={() => {}}>
            <p>content</p>
          </BaseModal>
        );

        fireEvent.keyDown(screen.getByRole('dialog'), { code: 'Escape' });

        expect(outerHandler).not.toHaveBeenCalled();
      } finally {
        document.removeEventListener('keydown', outerHandler);
      }
    });

    it('Escape 以外のキーでは onClose は呼ばれない', () => {
      const handleClose = vi.fn();
      render(
        <BaseModal isOpen onClose={handleClose}>
          <p>content</p>
        </BaseModal>
      );

      fireEvent.keyDown(screen.getByRole('dialog'), { code: 'Enter' });

      expect(handleClose).not.toHaveBeenCalled();
    });
  });

  describe('開閉ライフサイクル', () => {
    beforeEach(() => {
      vi.useFakeTimers();
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('isOpen 直後は afterOpenClassName が付かず、rAF 後に付与される', async () => {
      render(
        <BaseModal isOpen onClose={() => {}} afterOpenClassName="is-after-open" id="m">
          <p>content</p>
        </BaseModal>
      );
      const root = document.getElementById('m');

      // 初期描画時点では afterOpen はまだ false
      expect(root).not.toHaveClass('is-after-open');

      // requestAnimationFrame を flush
      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      expect(root).toHaveClass('is-after-open');
    });

    it('isOpen を false に切り替えると beforeCloseClassName が付与される', async () => {
      const { rerender } = render(
        <BaseModal
          isOpen
          onClose={() => {}}
          afterOpenClassName="is-after-open"
          beforeCloseClassName="is-before-close"
          id="m"
          closeTimeout={300}
        >
          <p>content</p>
        </BaseModal>
      );
      const root = document.getElementById('m');

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });
      expect(root).toHaveClass('is-after-open');

      rerender(
        <BaseModal
          isOpen={false}
          onClose={() => {}}
          afterOpenClassName="is-after-open"
          beforeCloseClassName="is-before-close"
          id="m"
          closeTimeout={300}
        >
          <p>content</p>
        </BaseModal>
      );

      // closeTimeout 経過前は dom 上に残っており、beforeClose 状態のクラスが付与される
      expect(document.getElementById('m')).toHaveClass('is-before-close');
      expect(document.getElementById('m')).not.toHaveClass('is-after-open');
    });

    it('closeTimeout 経過後にアンマウントされる', async () => {
      const { rerender } = render(
        <BaseModal isOpen onClose={() => {}} closeTimeout={300} id="m">
          <p>content</p>
        </BaseModal>
      );

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      rerender(
        <BaseModal isOpen={false} onClose={() => {}} closeTimeout={300} id="m">
          <p>content</p>
        </BaseModal>
      );

      expect(document.getElementById('m')).not.toBeNull();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(300);
      });

      expect(document.getElementById('m')).toBeNull();
    });

    it('onAfterOpen / onAfterClose がそれぞれ呼ばれる', async () => {
      const onAfterOpen = vi.fn();
      const onAfterClose = vi.fn();
      const { rerender } = render(
        <BaseModal isOpen onClose={() => {}} onAfterOpen={onAfterOpen} onAfterClose={onAfterClose} closeTimeout={100}>
          <p>content</p>
        </BaseModal>
      );

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });
      expect(onAfterOpen).toHaveBeenCalledTimes(1);
      expect(onAfterClose).not.toHaveBeenCalled();

      rerender(
        <BaseModal
          isOpen={false}
          onClose={() => {}}
          onAfterOpen={onAfterOpen}
          onAfterClose={onAfterClose}
          closeTimeout={100}
        >
          <p>content</p>
        </BaseModal>
      );

      await act(async () => {
        await vi.advanceTimersByTimeAsync(100);
      });

      expect(onAfterClose).toHaveBeenCalledTimes(1);
    });
  });
});
