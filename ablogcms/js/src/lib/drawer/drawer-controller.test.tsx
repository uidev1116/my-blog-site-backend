import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, act, waitFor, cleanup } from '@testing-library/react';
import DrawerController from './drawer-controller';
import type DrawerManager from './store/drawer-manager';

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

describe('DrawerController', () => {
  beforeEach(() => {
    setupAcmsMock();
    document.body.innerHTML = '';
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    delete (DrawerController as { openTrigger?: string }).openTrigger;
    delete (DrawerController as { closeTrigger?: string }).closeTrigger;
    DrawerController.openTrigger = '.js-acms-drawer-open';
    DrawerController.closeTrigger = '.js-acms-drawer-close';
  });

  test('manager を ACMS.Library.drawer に登録する', () => {
    render(<DrawerController />);
    expect(acmsMock().Library.drawer).toBeDefined();
  });

  test('openTrigger の click でテンプレート由来のコンテンツを描画する', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = `
      <div data-drawer-section="header">タイトル</div>
      <div data-drawer-section="body">本文です</div>
      <div data-drawer-section="footer">フッター</div>
    `;
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-drawer-open';
    trigger.dataset.target = '#drawer-source';
    document.body.appendChild(trigger);

    render(<DrawerController />);

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

  test('data-drawer-placement が Drawer の placement に反映される', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">本文</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-drawer-open';
    trigger.dataset.target = '#drawer-source';
    trigger.dataset.drawerPlacement = 'left';
    document.body.appendChild(trigger);

    render(<DrawerController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(document.querySelector('.acms-admin-drawer-left')).not.toBeNull();
    });
  });

  test('closeTrigger の click でドロワーが閉じる', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">閉じる前</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-drawer-open';
    openBtn.dataset.target = '#drawer-source';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-drawer-close';
    document.body.appendChild(closeBtn);

    render(<DrawerController />);

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

  test('data-drawer-header-* / body-* / footer-* の prefix 別 props がパースされる', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = `
      <div data-drawer-section="header">タイトル</div>
      <div data-drawer-section="body">本文</div>
      <div data-drawer-section="footer">フッター</div>
    `;
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-drawer-open';
    trigger.dataset.target = '#drawer-source';
    trigger.dataset.drawerHeaderClassName = 'my-header';
    trigger.dataset.drawerBodyClassName = 'my-body';
    trigger.dataset.drawerFooterClassName = 'my-footer';
    document.body.appendChild(trigger);

    render(<DrawerController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    await waitFor(() => {
      expect(document.querySelector('header.my-header')).not.toBeNull();
    });
    expect(document.querySelector('.acms-admin-drawer-body.my-body')).not.toBeNull();
    expect(document.querySelector('footer.my-footer')).not.toBeNull();
  });

  test('開いた状態で content.raw が変わると hydrate (Dispatch) が再実行される', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">初期</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-acms-drawer-open';
    trigger.dataset.target = '#drawer-source';
    document.body.appendChild(trigger);

    render(<DrawerController />);
    await act(async () => {
      fireEvent.click(trigger);
    });

    // 初回 hydrate を待つ
    await waitFor(() => {
      expect(acmsMock().Dispatch).toHaveBeenCalled();
    });
    const initialCalls = acmsMock().Dispatch.mock.calls.length;

    // submit 後を模して manager.setState で content を差し替える
    const manager = acmsMock().Library.drawer as DrawerManager;
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

    const { unmount } = render(<DrawerController />);

    const addedClickHandlers = addSpy.mock.calls.filter(([type]) => type === 'click').length;
    expect(addedClickHandlers).toBeGreaterThanOrEqual(2); // open / close

    unmount();

    const removedClickHandlers = removeSpy.mock.calls.filter(([type]) => type === 'click').length;
    expect(removedClickHandlers).toBe(addedClickHandlers);
  });

  test('閉じきった後は openStateRef がリセットされ、再オープン時は onAfterOpen 経由で hydrate される', async () => {
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">本文</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-drawer-open';
    openBtn.dataset.target = '#drawer-source';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-drawer-close';
    document.body.appendChild(closeBtn);

    render(<DrawerController />);

    // 1回目
    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => expect(acmsMock().Dispatch).toHaveBeenCalled());
    const firstCallCount = acmsMock().Dispatch.mock.calls.length;

    // 閉じる (closeTimeout=300ms 経過後に onAfterClose が走る)
    await act(async () => {
      fireEvent.click(closeBtn);
    });
    await new Promise((resolve) => {
      setTimeout(resolve, 350);
    });

    // 2回目
    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => {
      expect(acmsMock().Dispatch.mock.calls.length).toBeGreaterThan(firstCallCount);
    });
  });

  test('close 直後は placement を保持し、onAfterClose 完了後に reset で初期化される', async () => {
    // 閉じるトランジション中に placement が消えると右へ消えるアニメになるため、
    // close() は破壊せず、onAfterClose 経由で reset() を呼ぶ仕様
    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">本文</div>';
    document.body.appendChild(template);

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'js-acms-drawer-open';
    openBtn.dataset.target = '#drawer-source';
    openBtn.dataset.drawerPlacement = 'left';
    document.body.appendChild(openBtn);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'js-acms-drawer-close';
    document.body.appendChild(closeBtn);

    render(<DrawerController />);

    await act(async () => {
      fireEvent.click(openBtn);
    });
    await waitFor(() => expect(screen.getByText('本文')).toBeTruthy());

    const manager = acmsMock().Library.drawer as DrawerManager;
    expect(manager.getSnapshot().drawerProps).toMatchObject({ placement: 'left', isOpen: true });

    // 閉じる直後は placement と content が保持されている (トランジション維持のため)
    await act(async () => {
      fireEvent.click(closeBtn);
    });
    expect(manager.getSnapshot().drawerProps).toMatchObject({ placement: 'left', isOpen: false });
    expect(manager.getSnapshot().content.body).toBe('本文');

    // onAfterClose 完了後に reset() が走り、placement / content がクリアされる
    await new Promise((resolve) => {
      setTimeout(resolve, 350);
    });
    await waitFor(() => {
      const snapshot = manager.getSnapshot();
      expect(snapshot.drawerProps).toEqual({ isOpen: false });
      expect(snapshot.content).toEqual({ raw: '' });
    });
  });

  test('カスタム openTrigger / closeTrigger を尊重する', async () => {
    DrawerController.openTrigger = '.js-custom-open';
    DrawerController.closeTrigger = '.js-custom-close';

    const template = document.createElement('template');
    template.id = 'drawer-source';
    template.innerHTML = '<div data-drawer-section="body">カスタム</div>';
    document.body.appendChild(template);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'js-custom-open';
    trigger.dataset.target = '#drawer-source';
    document.body.appendChild(trigger);

    // 既定の class はマッチしないこと
    const ignored = document.createElement('button');
    ignored.type = 'button';
    ignored.className = 'js-acms-drawer-open';
    ignored.dataset.target = '#drawer-source';
    document.body.appendChild(ignored);

    render(<DrawerController />);

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
