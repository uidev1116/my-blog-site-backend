import { forwardRef, useEffect, useRef, useState } from 'react';
import type { UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { Editor } from '@features/unit-editor/core';
import useEffectOnce from '@hooks/use-effect-once';
import useUpdateEffect from '@hooks/use-update-effect';
import useMergeRefs from '../../../../../hooks/use-merge-refs';
import { extract } from '../../utlls';

/**
 * ユニットフォームのAPIパラメータ
 */
interface FetchUnitHtmlParams {
  /**
   * ユニットの種類
   */
  unit: UnitTreeNode;
}

function normalizeUnit(unit: UnitTreeNode): UnitTreeNode {
  const newAttributes = { ...unit.attributes };

  // ユニットIDが含まれないキーがある場合は、ユニットIDを含むキーに変更する
  Object.keys(newAttributes).forEach((key) => {
    if (!key.includes(unit.id)) {
      // ユニットIDを含むキーに変更する
      const newKey = `${key}_${unit.id}`;
      newAttributes[newKey] = newAttributes[key];
      delete newAttributes[key];
    }
  });

  return { ...unit, attributes: newAttributes };
}

function createUrl(params: FetchUnitHtmlParams, options: RequestInit = {}): string {
  const { unit } = params;

  const url = ACMS.Library.acmsLink(
    {
      tpl: 'ajax/unit/render.html',
      searchParams: {
        id: unit.id,
        type: unit.type,
        ...(options.method === 'GET' ? normalizeUnit(unit).attributes : {}),
      },
    },
    {
      inherit: true,
      ajaxCacheBusting: false,
    }
  );

  return url;
}

function createCacheKey(params: FetchUnitHtmlParams): string {
  const { unit } = params;
  return ACMS.Library.acmsLink(
    {
      tpl: 'ajax/unit/render.html',
      searchParams: {
        id: unit.id,
        type: unit.type,
        ...normalizeUnit(unit).attributes,
      },
    },
    {
      inherit: true,
      ajaxCacheBusting: false,
    }
  );
}

function attributesToFormData(attributes: UnitTreeNode['attributes']): FormData {
  const formData = new FormData();

  Object.entries(attributes).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.forEach((v) => {
        formData.append(`${key}[]`, String(v));
      });
    } else {
      formData.append(key, String(value));
    }
  });
  return formData;
}

// キャッシュ用のMap
const cache = new Map<string, string>();

/**
 * ユニットの編集UIをHTMLで取得するAPI
 * @param params APIパラメータ
 * @param options オプション
 * @returns HTML文字列
 * @throws Error API呼び出しに失敗した場合
 */
export async function fetchUnitHtml(params: FetchUnitHtmlParams, options: RequestInit = {}): Promise<string> {
  const url = createUrl(params, options);
  const cacheKey = createCacheKey(params);

  // キャッシュに存在する場合はキャッシュから返す
  const cachedHtmlString = cache.get(cacheKey);
  if (cachedHtmlString !== undefined) {
    return cachedHtmlString;
  }

  const response = await fetch(url, {
    ...options,
    body: options.method === 'POST' ? attributesToFormData(params.unit.attributes) : undefined,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-Csrf-Token': window.csrfToken || '',
      ...options.headers,
    },
  });

  if (!response.ok) {
    throw new Error('Failed to fetch unit HTML');
  }

  const htmlString = await response.text();

  if (!htmlString) {
    throw new Error('Empty response from unit HTML');
  }

  const parser = new DOMParser();
  const doc = parser.parseFromString(htmlString, 'text/html');
  const html = doc.body.innerHTML;

  // キャッシュに保存
  cache.set(cacheKey, html);

  return html;
}

export interface ServerSideRendererProps extends React.HTMLAttributes<HTMLDivElement> {
  unit: UnitTreeNode;
  onRender?: (element: HTMLElement) => void | (() => void);
  editor: Editor;
  httpMethod?: 'GET' | 'POST';
}

const ServerSideRenderer = forwardRef<HTMLElement, ServerSideRendererProps>(
  ({ unit, onRender, editor, httpMethod = 'GET', ...props }, forwardedRef) => {
    const ref = useRef<HTMLElement>(null);
    const isEffected = useRef(false);
    const [html, setHtml] = useState<string>('');

    useEffectOnce(() => {
      if (isEffected.current) {
        // StrictMode の2回目のレンダリングでは実行しない
        // 2回目のレンダリングでは、unit.defaultHtml が削除されているため、初期表示のデータが失われてしまう
        return;
      }
      isEffected.current = true;
      if (unit.defaultHtml !== undefined && unit.defaultHtml !== '') {
        setHtml(unit.defaultHtml);
        delete unit.defaultHtml;
        return;
      }

      (async () => {
        const newHtml = await fetchUnitHtml({ unit: normalizeUnit(unit) }, { method: httpMethod });
        if (newHtml !== html) {
          setHtml(newHtml);
        }
      })();
    });

    useUpdateEffect(() => {
      let cleanup: void | (() => void);
      if (ref.current) {
        const attributes = extract(ref.current);
        unit.attributes = { ...attributes };
        editor.emit('serverSideUnitRender', {
          editor,
          unit,
          element: ref.current,
        });
        cleanup = onRender?.(ref.current);
      }
      return () => {
        cleanup?.();
      };
      // htmlの変更時にのみ実行
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [html]);

    useEffect(() => {
      const handleBeforeTransaction = () => {
        if (ref.current) {
          const attributes = extract(ref.current);
          unit.attributes = { ...attributes };
        }
      };

      editor.on('beforeTransaction', handleBeforeTransaction);
      return () => {
        editor.off('beforeTransaction', handleBeforeTransaction);
      };
    }, [unit, editor]);

    return (
      // eslint-disable-next-line react/no-danger
      <div ref={useMergeRefs(ref, forwardedRef)} {...props} dangerouslySetInnerHTML={{ __html: html }} />
    );
  }
);

ServerSideRenderer.displayName = 'ServerSideRenderer';

export default ServerSideRenderer;
