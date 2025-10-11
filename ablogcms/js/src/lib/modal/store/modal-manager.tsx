import type Modal from '../../../components/modal/modal';
import { fetchContentFromUrl, getContentFromSelector, patchFormActions } from './content-fetcher';
import SecurityManager, { SecurityConfig } from './security-manager';
import { pending } from '../../pending';

type ModalProps = Omit<React.ComponentProps<typeof Modal>, 'onClose'>;
type ModalHeaderProps = Omit<React.ComponentProps<typeof Modal.Header>, 'children'>;
type ModalBodyProps = Omit<React.ComponentProps<typeof Modal.Body>, 'children'>;
type ModalFooterProps = Omit<React.ComponentProps<typeof Modal.Footer>, 'children'>;

interface ModalManagerState {
  content: Content;
  modalProps: ModalProps;
  modalHeaderProps: ModalHeaderProps;
  modalBodyProps: ModalBodyProps;
  modalFooterProps: ModalFooterProps;
}

export interface ModalManagerOptions {
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

interface ModalManagerOpenOptions {
  selector?: string;
  url?: string;
  modalProps?: Partial<ModalProps>;
  modalHeaderProps?: Partial<ModalHeaderProps>;
  modalBodyProps?: Partial<ModalBodyProps>;
  modalFooterProps?: Partial<ModalFooterProps>;
}

const defaultOptions: ModalManagerOptions = {
  header: '[data-modal-section="header"]',
  body: '[data-modal-section="body"]',
  footer: '[data-modal-section="footer"]',
  security: {},
};

class ModalManager {
  private listeners = new Set<() => void>();

  private state: ModalManagerState = {
    content: {
      raw: '',
    },
    modalProps: {
      isOpen: false,
    },
    modalHeaderProps: {},
    modalBodyProps: {},
    modalFooterProps: {},
  };

  #container: HTMLElement | null = null;

  private config: ModalManagerOptions = defaultOptions;

  private securityManager: SecurityManager;

  constructor(options: React.MutableRefObject<Partial<ModalManagerOptions>>) {
    this.config = { ...defaultOptions, ...options.current };
    this.securityManager = new SecurityManager(this.config.security);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.open = this.open.bind(this);
    this.close = this.close.bind(this);
    this.init();
  }

  subscribe = (callback: () => void) => {
    this.listeners.add(callback);
    return () => this.listeners.delete(callback);
  };

  getSnapshot = () => this.state;

  public setState(state: ModalManagerState | ((state: ModalManagerState) => ModalManagerState)) {
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

  public get modalProps() {
    return this.state.modalProps;
  }

  public get modalHeaderProps() {
    return this.state.modalHeaderProps;
  }

  public get modalBodyProps() {
    return this.state.modalBodyProps;
  }

  public get modalFooterProps() {
    return this.state.modalFooterProps;
  }

  public get container() {
    return this.#container;
  }

  private init() {
    // モーダル用のコンテナ要素を作成
    const container = document.getElementById('acms-modal-manager-container');
    if (container !== null) {
      this.#container = container;
    } else {
      this.#container = document.createElement('div');
      this.#container.id = 'acms-modal-manager-container';
      document.body.appendChild(this.#container);
    }
    this.registerEventListeners();
  }

  async open(options: ModalManagerOpenOptions = {}) {
    const removeSplash = pending.splash(ACMS.i18n('splash.default'));
    try {
      // コンテンツ取得ユーティリティを使用
      const element = await this.fetchContent(options);

      // コンテンツをパース
      const content = this.parseContent(element);

      // 状態を更新
      this.setState({
        content,
        modalProps: {
          ...options.modalProps,
          isOpen: true,
        },
        modalHeaderProps: {
          ...options.modalHeaderProps,
        },
        modalBodyProps: {
          ...options.modalBodyProps,
        },
        modalFooterProps: {
          ...options.modalFooterProps,
        },
      });
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('Failed to open modal:', error);
      // エラーを再スローして呼び出し元でハンドリングできるようにする
      throw error;
    } finally {
      removeSplash();
    }
  }

  close() {
    // 状態を更新
    this.setState({
      content: {
        raw: '',
      },
      modalProps: {
        isOpen: false,
      },
      modalHeaderProps: {},
      modalBodyProps: {},
      modalFooterProps: {},
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
          if (confirm(ACMS.i18n('modal.reload'))) {
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

  private fetchContent(options: ModalManagerOpenOptions) {
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

export default ModalManager;
