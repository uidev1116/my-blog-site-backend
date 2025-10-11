import { ReactNode, StrictMode, ReactElement } from 'react';
import { createRoot, type Container, type Root, type RootOptions } from 'react-dom/client';
import { toCamelCase } from './string';

type ReactRootContainer = Container & {
  _reactRoot?: Root;
};

/**
 * 指定されたコンテナ要素に React コンポーネントツリーをレンダリングします。
 * もしコンテナに既存の React コンポーネントがマウントされている場合は、コンポーネントのレンダリングは行わず、既存の React ルートのインスタンスを返します。
 * また、自動的に StrictMode でラップされます。
 *
 * @param {ReactNode} children - コンテナ内にレンダリングする React 要素。
 * @param {ReactRootContainer} container - React コンポーネントツリーのコンテナとなる DOM 要素。
 * @param {RootOptions} [options] - ルートのオプション。
 * @returns {Root} - 作成された React ルートのインスタンス。
 */
export function render(children: ReactNode, container: ReactRootContainer, options?: RootOptions): Root {
  if (container._reactRoot) {
    // もし、mount済みの場合は、既存の React ルートのインスタンスを返す
    return container._reactRoot;
  }

  const root = createRoot(container, options);
  container._reactRoot = root;
  root.render(<StrictMode>{children}</StrictMode>);

  return root;
}

export function nl2br<T>(input: T | string): (string | ReactElement)[] | T {
  if (typeof input === 'string') {
    const newlineRegex = /\r\n|\n|\r/g;
    return input.split(newlineRegex).flatMap(
      (part, index, array) => (index < array.length - 1 ? [part, <br key={index} />] : [part]) // eslint-disable-line react/no-array-index-key
    );
  }
  return input;
}

export type ReactRef<T> = React.Ref<T> | React.MutableRefObject<T>;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function assignRef<T = any>(ref: ReactRef<T> | undefined, value: T) {
  if (ref == null) return;

  if (typeof ref === 'function') {
    ref(value);
    return;
  }

  try {
    // @ts-expect-error https://github.com/chakra-ui/chakra-ui/blob/fd231f720965b505faf5a0d8153366f8989ec9ce/packages/hooks/src/use-merge-refs.ts
    ref.current = value;
  } catch {
    throw new Error(`Cannot assign value '${value}' to ref '${ref}'`);
  }
}

export function handleRef<T>(...refs: (ReactRef<T> | undefined)[]) {
  if (refs.every((ref) => ref == null)) {
    return null;
  }
  return (node: T) => {
    refs.forEach((ref) => {
      if (ref) {
        assignRef(ref, node);
      }
    });
  };
}

function isJsonString(str: string): boolean {
  try {
    const parsed = JSON.parse(str);
    return typeof parsed === 'object' && parsed !== null;
  } catch {
    return false;
  }
}

/**
 * datasetToProps
 * @param dataset - HTMLElement.dataset
 * @param prefix - プレフィックス
 * @returns props オブジェクト (動的キー & 型推論 & camelCase)
 */
export function datasetToProps<T extends Record<string, unknown>>(dataset: DOMStringMap, prefix?: string): T {
  const props: Record<string, unknown> = {};

  const normalizedPrefix = prefix ? toCamelCase(prefix) : '';

  for (const [key, value] of Object.entries(dataset)) {
    // prefix 指定ありなら prefix に一致しないものはスキップ
    if (normalizedPrefix && !key.startsWith(normalizedPrefix)) {
      continue;
    }

    // prefix 指定ありなら prefix 部分を除外する
    const keyWithoutPrefix = normalizedPrefix ? key.slice(normalizedPrefix.length) : key;

    // 先頭を小文字化して camelCase 変換
    const normalizedKey = keyWithoutPrefix.charAt(0).toLowerCase() + keyWithoutPrefix.slice(1);
    const camelKey = toCamelCase(normalizedKey);

    if (value === 'true') {
      props[camelKey] = true;
    } else if (value === 'false') {
      props[camelKey] = false;
    } else if (value === 'null') {
      props[camelKey] = null;
    } else if (value === 'undefined') {
      props[camelKey] = undefined;
    } else if (value === 'NaN') {
      props[camelKey] = NaN;
    } else if (value === 'Infinity') {
      props[camelKey] = Infinity;
    } else if (value === '-Infinity') {
      props[camelKey] = -Infinity;
    } else if (typeof window[value as keyof Window] === 'function') {
      props[camelKey] = window[value as keyof Window];
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } else if (!isNaN(value as any) && value?.trim() !== '') {
      props[camelKey] = Number(value);
    } else if (isJsonString(value as string)) {
      props[camelKey] = JSON.parse(value as string);
    } else {
      props[camelKey] = value;
    }
  }

  return props as T;
}
