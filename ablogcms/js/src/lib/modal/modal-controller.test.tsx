import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, act, waitFor, cleanup } from '@testing-library/react';
import ModalController from './modal-controller';
import type ModalManager from './store/modal-manager';

const setupAcmsMock = () => {
  const i18n = vi.fn((key: string) => key);
  (i18n as unknown as { lng: string }).lng = 'ja';
  // 既存の acms 型を満たさない最小モック。テスト用途に限り as unknown 経由で差し込む
  (window as unknown as { ACMS: unknown }).ACMS = {
    i18n,
    Library: {},
    Dispatch: vi.fn(),
    dispatchEvent: vi.fn(),
  };
};

const acmsMock = () =>
  window.ACMS as unknown as {
    Library: Record<string, unknown>;
    Dispatch: ReturnType<typeof vi.fn>;
    dispatchEvent: ReturnType<typeof vi.fn>;
  };

describe('ModalController', () => {
  beforeEach(() => {
    setupAcmsMock();
    document.body.innerHTML = '';
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    delete (ModalController as { openTrigger?: string }).openTrigger;
    delete (ModalController as { closeTrigger?: string }).closeTrigger;
    ModalController.openTrigger = '.js-acms-modal-open';
    ModalController.closeTrigger = '.js-acms-modal-close';
  });

  test('manager を ACMS.Library.modal に登録する', () => {
    render(<ModalController />);
    expect(acmsMock().Library.modal).toBeDefined();
  });

  test('openTrigger の click でテンプレート由来のコンテンツを描画する', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = `
      <div data-modal-section="header">タイトル</div>
      <div data-modal-section="body">本文です</div>
      <div data-modal-section="footer">フッター</div>
    `;
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-modal-open';
    trigger.dataset.target = '#modal-source';
    document.body.appendChild(trigger);

    render(<ModalController />);

    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(screen.getByText('本文です')).toBeTruthy();
    });
    expect(screen.getByText('タイトル')).toBeTruthy();
    expect(screen.getByText('フッター')).toBeTruthy();
    // onAfterOpen は requestAnimationFrame 経由で発火するので待つ
    await waitFor(() => {
      expect(acmsMock().Dispatch).toHaveBeenCalled();
    });
    expect(acmsMock().dispatchEvent).toHaveBeenCalledWith(
      'acmsDialogOpened',
      expect.any(HTMLElement),
      expect.objectContaining({ item: expect.any(HTMLElement) })
    );
  });

  test('data-modal-size / is-centered / is-scrollable が Modal に反映される', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">本文</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-modal-open';
    trigger.dataset.target = '#modal-source';
    trigger.dataset.modalSize = 'large';
    trigger.dataset.modalIsCentered = 'true';
    trigger.dataset.modalIsScrollable = 'true';
    document.body.appendChild(trigger);

    render(<ModalController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(document.querySelector('.acms-admin-modal-dialog.large')).not.toBeNull();
    });
    expect(document.querySelector('.acms-admin-modal-dialog-centered')).not.toBeNull();
    expect(document.querySelector('.acms-admin-modal-dialog-scrollable')).not.toBeNull();
  });

  test('data-modal-header-* / body-* / footer-* の prefix 別 props がパースされる', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = `
      <div data-modal-section="header">タイトル</div>
      <div data-modal-section="body">本文</div>
      <div data-modal-section="footer">フッター</div>
    `;
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-modal-open';
    trigger.dataset.target = '#modal-source';
    trigger.dataset.modalHeaderClassName = 'my-header';
    trigger.dataset.modalBodyClassName = 'my-body';
    trigger.dataset.modalFooterClassName = 'my-footer';
    document.body.appendChild(trigger);

    render(<ModalController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(document.querySelector('header.my-header')).not.toBeNull();
    });
    expect(document.querySelector('.acms-admin-modal-body.my-body')).not.toBeNull();
    expect(document.querySelector('footer.my-footer')).not.toBeNull();
  });

  test('Modal.Body の tabContentScrollable オプションがクラスとして反映される', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">本文</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-modal-open';
    trigger.dataset.target = '#modal-source';
    trigger.dataset.modalBodyTabContentScrollable = 'true';
    document.body.appendChild(trigger);

    render(<ModalController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(document.querySelector('.acms-admin-modal-body-tab-scrollable')).not.toBeNull();
    });
  });

  test('closeTrigger の click でモーダルが閉じる', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">閉じる前</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-modal-open';
    openBtn.dataset.target = '#modal-source';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-modal-close';
    document.body.appendChild(closeBtn);

    render(<ModalController />);

    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => {
      expect(screen.getByText('閉じる前')).toBeTruthy();
    });

    await act(async () => {
      fireEvent.click(closeBtn);
    });

    await waitFor(() => {
      expect(screen.queryByText('閉じる前')).toBeNull();
    });
  });

  test('開いた状態で content.raw が変わると hydrate (Dispatch) が再実行される', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">初期</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-modal-open';
    trigger.dataset.target = '#modal-source';
    document.body.appendChild(trigger);

    render(<ModalController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(acmsMock().Dispatch).toHaveBeenCalled();
    });
    const initialCalls = acmsMock().Dispatch.mock.calls.length;

    const manager = acmsMock().Library.modal as ModalManager;
    await act(async () => {
      manager.setState((prev) => ({
        ...prev,
        content: { raw: '<div>更新後</div>', body: '<div>更新後</div>' },
      }));
    });

    await waitFor(() => {
      expect(acmsMock().Dispatch.mock.calls.length).toBeGreaterThan(initialCalls);
    });
  });

  test('アンマウントすると document の click リスナーが解除される', async () => {
    const addSpy = vi.spyOn(document, 'addEventListener');
    const removeSpy = vi.spyOn(document, 'removeEventListener');

    const { unmount } = render(<ModalController />);

    const addedClickHandlers = addSpy.mock.calls.filter(([type]) => type === 'click').length;
    expect(addedClickHandlers).toBeGreaterThanOrEqual(2);

    unmount();

    const removedClickHandlers = removeSpy.mock.calls.filter(([type]) => type === 'click').length;
    expect(removedClickHandlers).toBe(addedClickHandlers);
  });

  test('閉じきった後は再オープン時に onAfterOpen 経由で hydrate される', async () => {
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">本文</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-modal-open';
    openBtn.dataset.target = '#modal-source';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-modal-close';
    document.body.appendChild(closeBtn);

    render(<ModalController />);

    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => expect(acmsMock().Dispatch).toHaveBeenCalled());
    const firstCallCount = acmsMock().Dispatch.mock.calls.length;

    await act(async () => {
      fireEvent.click(closeBtn);
    });
    await new Promise((resolve) => {
      setTimeout(resolve, 350);
    });

    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => {
      expect(acmsMock().Dispatch.mock.calls.length).toBeGreaterThan(firstCallCount);
    });
  });

  test('close 直後は size などを保持し、onAfterClose 完了後に reset で初期化される', async () => {
    // 閉じるトランジション中に size 等が消えると見た目が一瞬切り替わるため、
    // close() は破壊せず、onAfterClose 経由で reset() を呼ぶ仕様
    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">本文</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-modal-open';
    openBtn.dataset.target = '#modal-source';
    openBtn.dataset.modalSize = 'large';
    openBtn.dataset.modalIsCentered = 'true';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-modal-close';
    document.body.appendChild(closeBtn);

    render(<ModalController />);

    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => expect(screen.getByText('本文')).toBeTruthy());

    const manager = acmsMock().Library.modal as ModalManager;
    expect(manager.getSnapshot().modalProps).toMatchObject({ size: 'large', isCentered: true, isOpen: true });

    // 閉じる直後は size / isCentered と content が保持されている (トランジション維持のため)
    await act(async () => {
      fireEvent.click(closeBtn);
    });
    expect(manager.getSnapshot().modalProps).toMatchObject({ size: 'large', isCentered: true, isOpen: false });
    expect(manager.getSnapshot().content.body).toBe('本文');

    // onAfterClose 完了後に reset() が走り、size / content がクリアされる
    await new Promise((resolve) => {
      setTimeout(resolve, 350);
    });
    await waitFor(() => {
      const snapshot = manager.getSnapshot();
      expect(snapshot.modalProps).toEqual({ isOpen: false });
      expect(snapshot.content).toEqual({ raw: '' });
    });
  });

  test('カスタム openTrigger / closeTrigger を尊重する', async () => {
    ModalController.openTrigger = '.js-custom-open';
    ModalController.closeTrigger = '.js-custom-close';

    const template = document.createElement('template');
    template.id = 'modal-source';
    template.innerHTML = '<div data-modal-section="body">カスタム</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-custom-open';
    trigger.dataset.target = '#modal-source';
    document.body.appendChild(trigger);

    const ignored = document.createElement('button');
    ignored.type = 'button';
    ignored.className = 'js-acms-modal-open';
    ignored.dataset.target = '#modal-source';
    document.body.appendChild(ignored);

    render(<ModalController />);

    await act(async () => {
      fireEvent.click(ignored);
    });
    expect(screen.queryByText('カスタム')).toBeNull();

    await act(async () => {
      fireEvent.click(trigger);
    });
    await waitFor(() => {
      expect(screen.getByText('カスタム')).toBeTruthy();
    });
  });
});
