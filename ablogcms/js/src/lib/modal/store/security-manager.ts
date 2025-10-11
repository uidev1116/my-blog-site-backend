/**
 * URL検証の設定
 */
export interface SecurityConfig {
  allowedDomains?: string[];
  allowSameOrigin: boolean;
  allowRelativeUrls: boolean;
}

/**
 * デフォルトのセキュリティ設定
 */
export const defaultOptions: SecurityConfig = {
  allowSameOrigin: true,
  allowRelativeUrls: true,
  allowedDomains: [],
};

/**
 * URLが安全かどうかを検証する関数
 */
export function validateUrl(url: string, config: SecurityConfig = defaultOptions): boolean {
  try {
    const urlObj = new URL(url, window.location.href);

    // 相対URLの許可チェック
    if (urlObj.protocol === 'file:' && !config.allowRelativeUrls) {
      return false;
    }

    // 同一オリジンの許可チェック
    if (urlObj.origin === window.location.origin) {
      return config.allowSameOrigin;
    }

    // 許可されたドメインのチェック
    if (config.allowedDomains && config.allowedDomains.length > 0) {
      return config.allowedDomains.some((domain) => {
        // ワイルドカードサポート（例: *.example.com）
        if (domain.startsWith('*.')) {
          const domainSuffix = domain.slice(2);
          return urlObj.hostname.endsWith(domainSuffix);
        }
        return urlObj.hostname === domain;
      });
    }

    // 許可されたドメインが設定されていない場合は拒否
    return false;
  } catch (error) {
    // eslint-disable-next-line no-console
    console.error('Invalid URL:', error);
    return false;
  }
}

/**
 * セキュリティ設定を管理するクラス
 */
export default class SecurityManager {
  #config: SecurityConfig = { ...defaultOptions };

  constructor(options: Partial<SecurityConfig> = {}) {
    this.#config = { ...defaultOptions, ...options };
  }

  /**
   * セキュリティ設定を更新
   */
  setConfig(newConfig: Partial<SecurityConfig>) {
    this.#config = { ...this.#config, ...newConfig };
  }

  /**
   * 現在の設定を取得
   */
  public get config(): SecurityConfig {
    return this.#config;
  }

  /**
   * URLを検証
   */
  public validateUrl(url: string): boolean {
    return validateUrl(url, this.#config);
  }
}
