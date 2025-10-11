import { redirect, matchPath, type RouteObject } from 'react-router';

import type { AcmsContext } from '../../acmsPath/types';
import { parseUrlSearchParams } from '../../../utils';

export interface CreateLoaderOptions {
  id: string;
  admin: string;
  onLoad: ({ context, url }: { context: AcmsContext; url: URL }) => void | Promise<void>;
  ignoredParams?: string[];
}

/**
 * ストレージキー生成関数
 */
function createStorageKey(id: string): string {
  return `acms-${id}-root-${ACMS.Config.root}-suid-${ACMS.Config.suid}-bid-${ACMS.Config.bid}`;
}

/**
 * ローダー関数を作成する
 */
export default function createLoader({
  id,
  admin,
  onLoad,
  ignoredParams = [],
}: CreateLoaderOptions): RouteObject['loader'] {
  const storageKey = createStorageKey(id);
  let isFirstMount = true;

  const loader: RouteObject['loader'] = ({ params, request }) => {
    const path = params['*'] as string;
    const url = new URL(request.url);
    const context = ACMS.Library.parseAcmsPath(decodeURI(path));
    const match = matchPath(`${ACMS.Config.root}bid/:bid/admin/${admin}`, url.pathname);

    if (isFirstMount && match && url.searchParams.size === 0) {
      // 初回アクセスかつ、検索条件がない場合はローカルストレージから取得
      const data = localStorage.getItem(storageKey);
      if (data) {
        // ローカルストレージにデータがある場合はリダイレクト
        const savedContext: AcmsContext & { searchParams: Record<string, string | string[]> } = JSON.parse(data);
        return redirect(ACMS.Library.acmsLink({ ...savedContext, bid: match.params.bid, admin }));
      }
    }

    if (match && url.searchParams.size === 0) {
      // 検索条件がない場合
      localStorage.removeItem(storageKey);
    } else {
      // 検索条件がある場合
      ignoredParams.forEach((param) => {
        url.searchParams.delete(param);
      });
      const data = {
        ...context,
        field: context.field?.toString(),
        searchParams: parseUrlSearchParams(url.searchParams),
      };
      localStorage.setItem(storageKey, JSON.stringify(data));
    }

    // プリロード処理の実行
    onLoad({ context, url });
    isFirstMount = false;

    return null;
  };

  return loader;
}
