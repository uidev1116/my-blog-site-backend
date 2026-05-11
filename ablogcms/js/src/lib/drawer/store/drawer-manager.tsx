import type Drawer from '../../../components/drawer/drawer';
import { fetchContentFromUrl, getContentFromSelector, patchFormActions } from '../../modal/store/content-fetcher';
import SecurityManager, { SecurityConfig } from '../../modal/store/security-manager';
import { pending } from '../../pending';

type DrawerProps = Omit<React.ComponentProps<typeof Drawer>, 'onClose'>;
type DrawerHeaderProps = Omit<React.ComponentProps<typeof Drawer.Header>, 'children'>;
type DrawerBodyProps = Omit<React.ComponentProps<typeof Drawer.Body>, 'children'>;
type DrawerFooterProps = Omit<React.ComponentProps<typeof Drawer.Footer>, 'children'>;

interface DrawerManagerState {
  content: Content;
  drawerProps: DrawerProps;
  drawerHeaderProps: DrawerHeaderProps;
  drawerBodyProps: DrawerBodyProps;
  drawerFooterProps: DrawerFooterProps;
}

export interface DrawerManagerOptions {
  header: string;
  body: string;
  footer: string;
  security: Partial<SecurityConfig>;
}

interface Content {
  header?: string;
  body?: string;
  footer?: string;
  raw: string;
}

interface DrawerManagerOpenOptions {
  selector?: string;
  url?: string;
  drawerProps?: Partial<DrawerProps>;
  drawerHeaderProps?: Partial<DrawerHeaderProps>;
  drawerBodyProps?: Partial<DrawerBodyProps>;
  drawerFooterProps?: Partial<DrawerFooterProps>;
}

const defaultOptions: DrawerManagerOptions = {
  header: '[data-drawer-section="header"]',
  body: '[data-drawer-section="body"]',
  footer: '[data-drawer-section="footer"]',
  security: {},
};

class DrawerManager {
  private listeners = new Set<() => void>();

  private state: DrawerManagerState = {
    content: {
      raw: '',
    },
    drawerProps: {
      isOpen: false,
    },
    drawerHeaderProps: {},
    drawerBodyProps: {},
    drawerFooterProps: {},
  };

  #container: HTMLElement | null = null;

  private config: DrawerManagerOptions = defaultOptions;

  private securityManager: SecurityManager;

  constructor(options: React.MutableRefObject<Partial<DrawerManagerOptions>>) {
    this.config = { ...defaultOptions, ...options.current };
    this.securityManager = new SecurityManager(this.config.security);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.open = this.open.bind(this);
    this.close = this.close.bind(this);
    this.reset = this.reset.bind(this);
    this.init();
  }

  subscribe = (callback: () => void) => {
    this.listeners.add(callback);
    return () => this.listeners.delete(callback);
  };

  getSnapshot = () => this.state;

  public setState(state: DrawerManagerState | ((state: DrawerManagerState) => DrawerManagerState)) {
    if (typeof state === 'function') {
      this.state = state(this.state);
    } else {
      this.state = state;
    }
    this.emit();
  }

  public get content() {
    return this.state.content;
  }

  public get drawerProps() {
    return this.state.drawerProps;
  }

  public get drawerHeaderProps() {
    return this.state.drawerHeaderProps;
  }

  public get drawerBodyProps() {
    return this.state.drawerBodyProps;
  }

  public get drawerFooterProps() {
    return this.state.drawerFooterProps;
  }

  public get container() {
    return this.#container;
  }

  private init() {
    // ドロワー用のコンテナ要素を作成
    const container = document.getElementById('acms-drawer-manager-container');
    if (container !== null) {
      this.#container = container;
    } else {
      this.#container = document.createElement('div');
      this.#container.id = 'acms-drawer-manager-container';
      document.body.appendChild(this.#container);
    }
    this.registerEventListeners();
  }

  async open(options: DrawerManagerOpenOptions = {}) {
    const removeSplash = pending.splash(ACMS.i18n('splash.default'));
    try {
      // コンテンツ取得ユーティリティを使用
      const element = await this.fetchContent(options);

      // コンテンツをパース
      const content = this.parseContent(element);

      // 状態を更新
      this.setState({
        content,
        drawerProps: {
          ...options.drawerProps,
          isOpen: true,
        },
        drawerHeaderProps: {
          ...options.drawerHeaderProps,
        },
        drawerBodyProps: {
          ...options.drawerBodyProps,
        },
        drawerFooterProps: {
          ...options.drawerFooterProps,
        },
      });
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('Failed to open drawer:', error);
      // エラーを再スローして呼び出し元でハンドリングできるようにする
      throw error;
    } finally {
      removeSplash();
    }
  }

  close() {
    // 閉じるトランジション中は placement などの drawerProps を保持する必要があるため、
    // isOpen のみを切り替える。完全な初期化は reset() を onAfterClose 経由で呼ぶ
    this.setState((prev) => ({
      ...prev,
      drawerProps: {
        ...prev.drawerProps,
        isOpen: false,
      },
    }));
  }

  reset() {
    // 閉じるトランジション完了後に呼ばれることを想定し、コンテンツと props をクリアする
    this.setState({
      content: {
        raw: '',
      },
      drawerProps: {
        isOpen: false,
      },
      drawerHeaderProps: {},
      drawerBodyProps: {},
      drawerFooterProps: {},
    });
  }

  private emit = () => {
    this.listeners.forEach((cb) => cb());
  };

  // クリーンアップ用メソッド
  destroy() {
    this.unregisterEventListeners();
    if (this.#container) {
      this.#container.remove();
      this.#container = null;
    }
  }

  private async handleSubmit(event: SubmitEvent) {
    if (event.defaultPrevented) {
      // バリデーターがフォームの送信をキャンセルしている場合は、何もしない
      return;
    }
    event.preventDefault();
    const form = event.target as HTMLFormElement;
    const { submitter } = event;
    const formData = new FormData(form, submitter);

    let isSuccess = false;

    const removeSplash = pending.splash(ACMS.i18n('splash.save'));
    try {
      const response = await fetch(form.action, {
        method: form.method,
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-Csrf-Token': window.csrfToken || '',
        },
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const html = await response.text();
      isSuccess = this.checkSuccessFromContent(html);
      const content = this.parseContent(patchFormActions(html, form.action));
      this.setState((prev) => ({
        ...prev,
        content,
      }));
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('Failed to submit form:', error);
    } finally {
      removeSplash();
      if (isSuccess) {
        setTimeout(() => {
          // 成功メッセージの表示が完了してから確認ダイアログを表示
          if (confirm(ACMS.i18n('drawer.reload'))) {
            window.location.reload();
          }
        }, 500);
      }
    }
  }

  private checkSuccessFromContent(html: string): boolean {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // 成功メッセージのセレクタを確認
    const successSelectors = [
      '.acms-admin-alert-info', // 成功アラート
      '.acms-admin-alert-success', // 成功アラート
      '[data-status="success"]', // 成功ステータス
      '.success-message', // 成功メッセージ
    ];

    // エラーメッセージのセレクタを確認
    const errorSelectors = [
      '.acms-admin-alert-danger', // エラーアラート
      '.acms-admin-alert-error', // エラーアラート
      '[data-status="error"]', // エラーステータス
      '.error-message', // エラーメッセージ
    ];

    // エラーメッセージが存在する場合は失敗
    for (const selector of errorSelectors) {
      if (doc.querySelector(selector)) {
        return false;
      }
    }

    // 成功メッセージが存在する場合は成功
    for (const selector of successSelectors) {
      if (doc.querySelector(selector)) {
        return true;
      }
    }

    // デフォルトは成功とみなす（エラーがない場合）
    return true;
  }

  private registerEventListeners() {
    this.#container?.addEventListener('submit', this.handleSubmit);
  }

  private unregisterEventListeners() {
    this.#container?.removeEventListener('submit', this.handleSubmit);
  }

  private parseContent(htmlContent: string) {
    const { header, body, footer } = this.config;
    const result: Content = {
      raw: htmlContent,
    };

    if (!header && !body && !footer) {
      // セレクタが指定されていない場合は、全体をボディとして扱う
      result.body = htmlContent;
      return result;
    }

    // 一時的なDOM要素を作成してHTMLを解析
    const div = document.createElement('div');
    div.innerHTML = htmlContent;

    // ヘッダーセクションの抽出
    if (header) {
      const headerElement = div.querySelector(header);
      if (headerElement) {
        result.header = headerElement.innerHTML;
      }
    }

    // ボディセクションの抽出
    if (body) {
      const bodyElement = div.querySelector(body);
      if (bodyElement) {
        result.body = bodyElement.innerHTML;
      }
    }

    // フッターセクションの抽出
    if (footer) {
      const footerElement = div.querySelector(footer);
      if (footerElement) {
        result.footer = footerElement.innerHTML;
      }
    }

    // セレクタが指定されていない場合は、対応するセクションに全体のコンテンツを設定
    if (!result.header && !result.body && !result.footer) {
      result.body = htmlContent;
    }

    return result;
  }

  private fetchContent(options: DrawerManagerOpenOptions) {
    const { selector, url } = options;

    if (selector) {
      return getContentFromSelector(selector);
    }

    if (url) {
      if (!this.securityManager.validateUrl(url)) {
        throw new Error(`URL is not allowed: ${url}`);
      }
      return fetchContentFromUrl(url);
    }

    throw new Error('No selector or URL provided');
  }
}

export default DrawerManager;
