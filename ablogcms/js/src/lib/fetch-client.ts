export interface FetchResponse<T> {
  data: T;
  status: number;
  statusText: string;
  headers: Headers;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export class FetchError<T = any> extends Error {
  status: number;

  statusText: string;

  response?: FetchResponse<T>;

  constructor(message: string, status: number, statusText: string, response?: FetchResponse<T>) {
    super(message);
    this.name = 'FetchError';
    this.status = status;
    this.statusText = statusText;
    this.response = response;
  }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function isFetchError<T = any>(error: unknown): error is FetchError<T> {
  return error instanceof FetchError;
}

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

export interface FetchClientConfig {
  params?: Record<string, string | number | boolean | null | undefined | string[]>;
  responseType?: 'json' | 'blob' | 'text';
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

function getDefaultHeaders(): Record<string, string> {
  return {
    'X-Requested-With': 'XMLHttpRequest',
    'X-Csrf-Token': window.csrfToken || '',
  };
}

function buildUrl(url: string, params?: FetchClientConfig['params']): string {
  if (!params) return url;
  const searchParams = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value != null) {
      if (Array.isArray(value)) {
        value.forEach((v) => searchParams.append(key, v));
      } else {
        searchParams.append(key, String(value));
      }
    }
  }
  const queryString = searchParams.toString();
  if (!queryString) return url;
  const separator = url.includes('?') ? '&' : '?';
  return `${url}${separator}${queryString}`;
}

async function parseResponse<T>(response: Response, responseType?: string): Promise<T> {
  if (responseType === 'blob') {
    return (await response.blob()) as T;
  }
  if (responseType === 'text') {
    return (await response.text()) as T;
  }
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return (await response.json()) as T;
  }
  return (await response.text()) as T;
}

async function request<T>(url: string, init: RequestInit, config: FetchClientConfig = {}): Promise<FetchResponse<T>> {
  const finalUrl = buildUrl(url, config.params);

  const headers: Record<string, string> = {
    ...getDefaultHeaders(),
    ...config.headers,
  };

  // FormData/URLSearchParamsの場合はContent-Typeを設定しない（ブラウザが自動設定）
  if (init.body && !(init.body instanceof FormData) && !(init.body instanceof URLSearchParams)) {
    headers['Content-Type'] = 'application/json';
  }

  const response = await fetch(finalUrl, {
    ...init,
    headers,
    signal: config.signal,
  });

  if (!response.ok) {
    const fetchResponse: FetchResponse<T> = {
      data: await parseResponse<T>(response, config.responseType),
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
    };
    throw new FetchError<T>(
      `Request failed with status ${response.status}`,
      response.status,
      response.statusText,
      fetchResponse
    );
  }

  const data = await parseResponse<T>(response, config.responseType);
  return {
    data,
    status: response.status,
    statusText: response.statusText,
    headers: response.headers,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function get<T = any>(url: string, config?: FetchClientConfig): Promise<FetchResponse<T>> {
  return request<T>(url, { method: 'GET' }, config);
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function post<T = any>(
  url: string,
  body?: FormData | URLSearchParams | Record<string, unknown> | null,
  config?: FetchClientConfig
): Promise<FetchResponse<T>> {
  let requestBody: BodyInit | undefined;
  if (body instanceof FormData || body instanceof URLSearchParams) {
    requestBody = body;
  } else if (body != null) {
    requestBody = JSON.stringify(body);
  }
  return request<T>(url, { method: 'POST', body: requestBody }, config);
}

export const fetchClient = { get, post };
