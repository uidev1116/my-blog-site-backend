import { useEffect, useState } from 'react';
import type hljs from 'highlight.js';
import type { HighlightOptions, HighlightResult } from 'highlight.js';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UseSyntaxHighlightOptions extends HighlightOptions {}

export default function useSyntaxHighlight(code: string, options: UseSyntaxHighlightOptions) {
  const [result, setResult] = useState<Omit<HighlightResult, '_illegalBy' | '_emitter' | '_top'>>({
    relevance: 0,
    value: '',
    illegal: false,
  });
  const [hljsInstance, setHljsInstance] = useState<typeof hljs | null>(null);

  useEffect(() => {
    const loadHljs = async () => {
      const { default: hljs } = await import(/* webpackChunkName: "highlightjs" */ 'highlight.js');
      setHljsInstance(hljs);
    };
    loadHljs();
  }, []);

  useEffect(() => {
    if (hljsInstance) {
      const newResult = hljsInstance.highlight(code, options);
      // 結果が変更されていない場合は状態を更新しない
      // これをしないと、無限ループになる
      if (result === null || newResult.value !== result.value) {
        setResult(newResult);
      }
    }
  }, [hljsInstance, options, code, result]);

  return result;
}
