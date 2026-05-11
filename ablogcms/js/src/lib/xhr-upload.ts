import { FetchError, type FetchResponse } from './fetch-client';

interface XhrUploadOptions {
  onUploadProgress?: (event: { loaded: number; total: number }) => void;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function xhrUpload<T = any>(
  url: string,
  formData: FormData,
  options?: XhrUploadOptions
): Promise<FetchResponse<T>> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.timeout = 120_000;
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-Csrf-Token', window.csrfToken || '');

    if (options?.onUploadProgress) {
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          options.onUploadProgress?.({ loaded: e.loaded, total: e.total });
        }
      });
    }

    xhr.addEventListener('load', () => {
      const headers = new Headers();
      const rawHeaders = xhr
        .getAllResponseHeaders()
        .trim()
        .split(/[\r\n]+/);
      for (const line of rawHeaders) {
        const index = line.indexOf(':');
        if (index > -1) {
          const key = line.slice(0, index).trim();
          const value = line.slice(index + 1).trim();
          headers.append(key, value);
        }
      }

      if (xhr.status < 200 || xhr.status >= 300) {
        reject(new FetchError(`Upload failed with status ${xhr.status}`, xhr.status, xhr.statusText));
        return;
      }

      const contentType = xhr.getResponseHeader('Content-Type') || '';
      if (contentType.includes('application/json')) {
        let parsed: T;
        try {
          parsed = JSON.parse(xhr.responseText) as T;
        } catch {
          reject(new TypeError('Failed to parse JSON response'));
          return;
        }
        resolve({
          data: parsed,
          status: xhr.status,
          statusText: xhr.statusText,
          headers,
        });
      } else {
        reject(new TypeError(`Unexpected Content-Type: ${contentType}`));
      }
    });

    xhr.addEventListener('error', () => {
      reject(new Error('Upload failed'));
    });

    xhr.addEventListener('timeout', () => {
      reject(new Error('Upload timed out'));
    });

    xhr.addEventListener('abort', () => {
      reject(new Error('Upload aborted'));
    });

    xhr.send(formData);
  });
}
