/**
 * フォームアクション補完機能
 */
export function patchFormActions(html: string, baseUrl: string): string {
  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // formのactionを補完
    doc.querySelectorAll('form').forEach((form) => {
      form.setAttribute('action', baseUrl);
    });

    return doc.body.innerHTML;
  } catch (error) {
    // eslint-disable-next-line no-console
    console.error('Failed to patch form actions:', error);
    return html;
  }
}

/**
 * URL からのコンテンツ取得
 */
export async function fetchContentFromUrl(url: string): Promise<string> {
  try {
    const response = await fetch(url, {
      headers: {
        'Content-Type': 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Csrf-Token': window.csrfToken || '',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Content-Typeの検証
    const contentType = response.headers.get('content-type');
    if (contentType && !contentType.includes('text/html')) {
      throw new Error(`Invalid content type: ${contentType}. Expected text/html`);
    }

    const html = await response.text();
    // フォームアクションの補完
    return patchFormActions(html, url);
  } catch (error) {
    // eslint-disable-next-line no-console
    console.error('Failed to fetch content from URL:', error);
    throw error;
  }
}

/**
 * セレクタからのコンテンツ取得
 */
export function getContentFromSelector(selector: string): string {
  try {
    const template = document.querySelector(selector);

    if (!template) {
      throw new Error(`Element not found: ${selector}`);
    }

    if (!(template instanceof HTMLTemplateElement)) {
      throw new Error(`Element is not a template: ${selector}`);
    }

    const clone = template.content.cloneNode(true) as DocumentFragment;
    const div = document.createElement('div');
    div.appendChild(clone);
    return div.innerHTML;
  } catch (error) {
    // eslint-disable-next-line no-console
    console.error('Failed to get content from selector:', error);
    throw error;
  }
}
