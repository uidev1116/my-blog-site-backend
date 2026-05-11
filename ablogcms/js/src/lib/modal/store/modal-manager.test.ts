import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import ModalManager from './modal-manager';

const createOptionsRef = (overrides: Partial<ConstructorParameters<typeof ModalManager>[0]['current']> = {}) =>
  ({ current: overrides }) as ConstructorParameters<typeof ModalManager>[0];

describe('ModalManager', () => {
  let manager: ModalManager;

  beforeEach(() => {
    document.body.innerHTML = '';
  });

  afterEach(() => {
    if (manager) {
      manager.destroy();
    }
    vi.restoreAllMocks();
  });

  describe('constructor', () => {
    test('管理用コンテナを body 直下に生成する', () => {
      manager = new ModalManager(createOptionsRef());

      const container = document.getElementById('acms-modal-manager-container');
      expect(container).not.toBeNull();
      expect(container?.parentElement).toBe(document.body);
      expect(manager.container).toBe(container);
    });

    test('既存コンテナがあれば再利用する', () => {
      const existing = document.createElement('div');
      existing.id = 'acms-modal-manager-container';
      document.body.appendChild(existing);

      manager = new ModalManager(createOptionsRef());

      expect(manager.container).toBe(existing);
      expect(document.querySelectorAll('#acms-modal-manager-container')).toHaveLength(1);
    });

    test('初期 state は閉じている', () => {
      manager = new ModalManager(createOptionsRef());

      const snapshot = manager.getSnapshot();
      expect(snapshot.modalProps.isOpen).toBe(false);
      expect(snapshot.content).toEqual({ raw: '' });
    });
  });

  describe('subscribe', () => {
    test('setState で購読者に通知する', () => {
      manager = new ModalManager(createOptionsRef());
      const listener = vi.fn();
      manager.subscribe(listener);

      manager.setState((prev) => ({ ...prev, content: { raw: 'updated' } }));

      expect(listener).toHaveBeenCalledTimes(1);
    });

    test('購読解除後は通知されない', () => {
      manager = new ModalManager(createOptionsRef());
      const listener = vi.fn();
      const unsubscribe = manager.subscribe(listener);
      unsubscribe();

      manager.setState((prev) => ({ ...prev, content: { raw: 'updated' } }));

      expect(listener).not.toHaveBeenCalled();
    });
  });

  describe('open / close', () => {
    test('selector 指定でテンプレートからコンテンツを抽出して isOpen=true になる', async () => {
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = `
        <div data-modal-section="header">タイトル</div>
        <div data-modal-section="body">本文</div>
        <div data-modal-section="footer">フッター</div>
      `;
      document.body.appendChild(template);

      manager = new ModalManager(createOptionsRef());
      await manager.open({ selector: '#modal-content' });

      const snapshot = manager.getSnapshot();
      expect(snapshot.modalProps.isOpen).toBe(true);
      expect(snapshot.content.header).toBe('タイトル');
      expect(snapshot.content.body).toBe('本文');
      expect(snapshot.content.footer).toBe('フッター');
    });

    test('open で渡した modalProps が state にマージされる', async () => {
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = '<div data-modal-section="body">本文</div>';
      document.body.appendChild(template);

      manager = new ModalManager(createOptionsRef());
      await manager.open({
        selector: '#modal-content',
        modalProps: { size: 'large', isCentered: true, isScrollable: true },
      });

      expect(manager.getSnapshot().modalProps).toMatchObject({
        isOpen: true,
        size: 'large',
        isCentered: true,
        isScrollable: true,
      });
    });

    test('selector も url も無いと例外を投げる', async () => {
      manager = new ModalManager(createOptionsRef());
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      await expect(manager.open({})).rejects.toThrow('No selector or URL provided');
      expect(consoleSpy).toHaveBeenCalled();
    });

    test('存在しないセレクタは例外を投げる', async () => {
      manager = new ModalManager(createOptionsRef());
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      await expect(manager.open({ selector: '#missing' })).rejects.toThrow();
      expect(consoleSpy).toHaveBeenCalled();
    });

    test('close は isOpen のみ false にし、size などの modalProps は保持する', async () => {
      // 閉じるトランジション中に size 等が消えると、Modal のデフォルト値に戻ってしまい
      // 見た目が一瞬切り替わる。これを防ぐため close では破壊しない
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = '<div data-modal-section="body">本文</div>';
      document.body.appendChild(template);

      manager = new ModalManager(createOptionsRef());
      await manager.open({
        selector: '#modal-content',
        modalProps: { size: 'large', isCentered: true },
        modalHeaderProps: { className: 'demo-header' },
      });
      manager.close();

      const snapshot = manager.getSnapshot();
      expect(snapshot.modalProps.isOpen).toBe(false);
      expect(snapshot.modalProps).toMatchObject({ size: 'large', isCentered: true });
      // content や header props もトランジション完了までは保持される
      expect(snapshot.content.body).toBe('本文');
      expect(snapshot.modalHeaderProps).toEqual({ className: 'demo-header' });
    });

    test('reset で content と props がすべてクリアされる', async () => {
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = '<div data-modal-section="body">本文</div>';
      document.body.appendChild(template);

      manager = new ModalManager(createOptionsRef());
      await manager.open({
        selector: '#modal-content',
        modalProps: { size: 'large', isCentered: true },
        modalHeaderProps: { className: 'demo-header' },
      });
      manager.close();
      manager.reset();

      const snapshot = manager.getSnapshot();
      expect(snapshot.modalProps).toEqual({ isOpen: false });
      expect(snapshot.content).toEqual({ raw: '' });
      expect(snapshot.modalHeaderProps).toEqual({});
      expect(snapshot.modalBodyProps).toEqual({});
      expect(snapshot.modalFooterProps).toEqual({});
    });
  });

  describe('parseContent', () => {
    test('セクションが無い HTML はそのまま body に入る', async () => {
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = '<p>plain</p>';
      document.body.appendChild(template);

      manager = new ModalManager(createOptionsRef());
      await manager.open({ selector: '#modal-content' });

      const { content } = manager.getSnapshot();
      expect(content.body).toContain('<p>plain</p>');
      expect(content.header).toBeUndefined();
      expect(content.footer).toBeUndefined();
    });

    test('カスタムセレクタを options で上書きできる', async () => {
      const template = document.createElement('template');
      template.id = 'modal-content';
      template.innerHTML = `
        <header class="custom-header">独自タイトル</header>
        <main class="custom-body">独自本文</main>
      `;
      document.body.appendChild(template);

      manager = new ModalManager(
        createOptionsRef({
          header: '.custom-header',
          body: '.custom-body',
          footer: '',
        })
      );
      await manager.open({ selector: '#modal-content' });

      const { content } = manager.getSnapshot();
      expect(content.header).toBe('独自タイトル');
      expect(content.body).toBe('独自本文');
    });
  });

  describe('URL からの取得とセキュリティ', () => {
    test('同一オリジン URL は許可されて fetch される', async () => {
      const fetchMock = vi.fn().mockResolvedValue(
        new Response('<div data-modal-section="body">remote</div>', {
          status: 200,
          headers: { 'content-type': 'text/html' },
        })
      );
      vi.stubGlobal('fetch', fetchMock);

      manager = new ModalManager(createOptionsRef());
      await manager.open({ url: '/some/path' });

      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(manager.getSnapshot().content.body).toContain('remote');
    });

    test('別オリジンかつ allowedDomains 未設定なら拒否する', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      manager = new ModalManager(
        createOptionsRef({
          security: { allowSameOrigin: false, allowRelativeUrls: false, allowedDomains: [] },
        })
      );

      await expect(manager.open({ url: 'https://example.com/foo' })).rejects.toThrow(/URL is not allowed/);
      expect(fetchMock).not.toHaveBeenCalled();
      expect(consoleSpy).toHaveBeenCalled();
    });

    test('allowedDomains の wildcard マッチで別オリジンを許可する', async () => {
      const fetchMock = vi.fn().mockResolvedValue(
        new Response('<div data-modal-section="body">remote</div>', {
          status: 200,
          headers: { 'content-type': 'text/html' },
        })
      );
      vi.stubGlobal('fetch', fetchMock);

      manager = new ModalManager(
        createOptionsRef({
          security: {
            allowSameOrigin: false,
            allowRelativeUrls: false,
            allowedDomains: ['*.example.com'],
          },
        })
      );

      await manager.open({ url: 'https://api.example.com/foo' });

      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(manager.getSnapshot().content.body).toContain('remote');
    });

    test('HTTP エラーレスポンスは例外として伝搬する', async () => {
      const fetchMock = vi.fn().mockResolvedValue(new Response('server error', { status: 500 }));
      vi.stubGlobal('fetch', fetchMock);
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      manager = new ModalManager(createOptionsRef());

      await expect(manager.open({ url: '/some/path' })).rejects.toThrow(/HTTP error/);
      expect(consoleSpy).toHaveBeenCalled();
    });

    test('text/html 以外の content-type は拒否する', async () => {
      const fetchMock = vi.fn().mockResolvedValue(
        new Response('{}', {
          status: 200,
          headers: { 'content-type': 'application/json' },
        })
      );
      vi.stubGlobal('fetch', fetchMock);
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      manager = new ModalManager(createOptionsRef());

      await expect(manager.open({ url: '/some/path' })).rejects.toThrow(/Invalid content type/);
      expect(consoleSpy).toHaveBeenCalled();
    });
  });

  describe('handleSubmit (フォーム送信)', () => {
    const submitFormInContainer = (container: HTMLElement, action: string, method = 'POST') => {
      const form = document.createElement('form');
      form.action = action;
      form.method = method;
      const input = document.createElement('input');
      input.name = 'foo';
      input.value = 'bar';
      form.appendChild(input);
      container.appendChild(form);

      const event = new Event('submit', { bubbles: true, cancelable: true });
      Object.defineProperty(event, 'submitter', { value: null, configurable: true });
      form.dispatchEvent(event);
      return { form, event };
    };

    test('submit で fetch が CSRF/AJAX ヘッダ付きで呼ばれる', async () => {
      (window as unknown as { csrfToken?: string }).csrfToken = 'token-xyz';
      const fetchMock = vi.fn().mockResolvedValue(
        new Response('<div data-modal-section="body">submitted</div>', {
          status: 200,
          headers: { 'content-type': 'text/html' },
        })
      );
      vi.stubGlobal('fetch', fetchMock);

      manager = new ModalManager(createOptionsRef());
      submitFormInContainer(manager.container as HTMLElement, '/submit');

      await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());

      const [calledUrl, calledInit] = fetchMock.mock.calls[0];
      // jsdom は form.action を絶対 URL に正規化する
      expect(calledUrl).toMatch(/\/submit$/);
      expect((calledInit as RequestInit).method).toMatch(/POST/i);
      expect((calledInit as RequestInit).body).toBeInstanceOf(FormData);
      expect((calledInit as RequestInit).headers).toMatchObject({
        'X-Requested-With': 'XMLHttpRequest',
        'X-Csrf-Token': 'token-xyz',
      });

      delete (window as unknown as { csrfToken?: string }).csrfToken;
    });

    test('submit 後にレスポンスから content が更新される', async () => {
      const fetchMock = vi.fn().mockResolvedValue(
        new Response('<div data-modal-section="body">after submit</div>', {
          status: 200,
          headers: { 'content-type': 'text/html' },
        })
      );
      vi.stubGlobal('fetch', fetchMock);

      manager = new ModalManager(createOptionsRef());
      submitFormInContainer(manager.container as HTMLElement, '/submit');

      await vi.waitFor(() => {
        expect(manager.getSnapshot().content.body).toContain('after submit');
      });
    });

    test('成功アラートが含まれていれば reload 確認ダイアログが表示される', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(
          new Response('<div data-modal-section="body">ok</div><div class="acms-admin-alert-success"></div>', {
            status: 200,
            headers: { 'content-type': 'text/html' },
          })
        );
        vi.stubGlobal('fetch', fetchMock);

        const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(false);

        manager = new ModalManager(createOptionsRef());
        submitFormInContainer(manager.container as HTMLElement, '/submit');

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
        await vi.waitFor(() => expect(manager.getSnapshot().content.body).toContain('ok'));
        await vi.advanceTimersByTimeAsync(500);

        expect(confirmMock).toHaveBeenCalledWith('modal.reload');
      } finally {
        vi.useRealTimers();
      }
    });

    test('エラーアラートが含まれていれば reload 確認ダイアログは出ない', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(
          new Response('<div data-modal-section="body">ng</div><div class="acms-admin-alert-danger"></div>', {
            status: 200,
            headers: { 'content-type': 'text/html' },
          })
        );
        vi.stubGlobal('fetch', fetchMock);
        const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(false);

        manager = new ModalManager(createOptionsRef());
        submitFormInContainer(manager.container as HTMLElement, '/submit');

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
        await vi.advanceTimersByTimeAsync(500);

        expect(confirmMock).not.toHaveBeenCalled();
      } finally {
        vi.useRealTimers();
      }
    });

    test('confirm で OK ならページ reload を実行する', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(
          new Response('<div data-modal-section="body">ok</div><div class="acms-admin-alert-success"></div>', {
            status: 200,
            headers: { 'content-type': 'text/html' },
          })
        );
        vi.stubGlobal('fetch', fetchMock);
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        const reloadMock = vi.fn();
        const originalLocation = window.location;
        Object.defineProperty(window, 'location', {
          configurable: true,
          value: { ...originalLocation, reload: reloadMock },
        });

        manager = new ModalManager(createOptionsRef());
        submitFormInContainer(manager.container as HTMLElement, '/submit');

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
        await vi.advanceTimersByTimeAsync(500);

        expect(reloadMock).toHaveBeenCalled();

        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation });
      } finally {
        vi.useRealTimers();
      }
    });

    test('event.defaultPrevented なバリデーション失敗時は fetch を呼ばない', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);

      manager = new ModalManager(createOptionsRef());
      const container = manager.container as HTMLElement;

      const form = document.createElement('form');
      form.action = '/submit';
      container.appendChild(form);

      container.addEventListener(
        'submit',
        (e) => {
          e.preventDefault();
        },
        { capture: true }
      );

      const event = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(event);

      await Promise.resolve();
      expect(fetchMock).not.toHaveBeenCalled();
    });
  });

  describe('destroy', () => {
    test('コンテナを DOM から取り除く', () => {
      manager = new ModalManager(createOptionsRef());
      const { container } = manager;
      expect(container?.isConnected).toBe(true);

      manager.destroy();

      expect(container?.isConnected).toBe(false);
      expect(manager.container).toBeNull();
    });

    test('destroy 後は submit イベントで fetch が呼ばれない', () => {
      manager = new ModalManager(createOptionsRef());
      const container = manager.container as HTMLElement;
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);

      const detachedContainer = container;
      manager.destroy();

      const form = document.createElement('form');
      form.action = '/submit';
      detachedContainer.appendChild(form);
      const event = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(event);

      expect(fetchMock).not.toHaveBeenCalled();
    });
  });
});
