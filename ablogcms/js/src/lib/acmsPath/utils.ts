export function formatDate(date: Date) {
  function twoDigits(num: number) {
    return num < 10 ? `0${num}` : num;
  }

  const year = date.getFullYear();
  const month = twoDigits(date.getMonth() + 1); // JavaScriptの月は0から始まるため、1を加える
  const day = twoDigits(date.getDate());
  const hours = twoDigits(date.getHours());
  const minutes = twoDigits(date.getMinutes());
  const seconds = twoDigits(date.getSeconds());

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

export function splitPath(path: string): string[] {
  if (path.startsWith('/')) {
    path = path.substring(1);
  }
  const result: string[] = [];
  let buffer: string = '';
  let isEscaped: boolean = false;
  for (let i = 0; i < path.length; i++) {
    const char = path[i];
    if (char === '\\' && !isEscaped) {
      // バックスラッシュを見つけたらエスケープ状態にする
      isEscaped = true;
      buffer += char; // バックスラッシュもバッファに追加
    } else if (char === '/' && !isEscaped) {
      // エスケープされていないスラッシュで分割
      result.push(buffer);
      buffer = '';
    } else {
      // 通常の文字をバッファに追加
      buffer += char;
      isEscaped = false; // 1文字進んだらエスケープ状態を解除
    }
  }
  // 最後の部分を追加
  if (buffer.length > 0) {
    result.push(buffer);
  }
  return result;
}

/**
 * プレーンオブジェクトかどうかを判定する
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function isPlainObject(value: any): value is Record<string, any> {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  // 配列やDate、その他の特殊なオブジェクトは除外
  if (Array.isArray(value) || value instanceof Date || value instanceof RegExp) {
    return false;
  }

  // プロトタイプがObjectまたはnullの場合のみtrue
  const proto = Object.getPrototypeOf(value);
  return proto === null || proto === Object.prototype;
}

/**
 * 設定オブジェクトを深くマージする関数
 * @param defaults - デフォルト設定
 * @param overrides - マージする設定（優先される）
 * @returns マージされた設定
 */
export function mergeConfig(
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  defaults: Record<string, any>,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  overrides: Record<string, any>
) {
  const result = { ...defaults };

  for (const key in overrides) {
    if (Object.prototype.hasOwnProperty.call(overrides, key)) {
      const overrideValue = overrides[key];
      const defaultValue = defaults[key];

      // undefined の場合はスキップ
      if (overrideValue === undefined) {
        continue;
      }

      // 両方がプレーンオブジェクトの場合は再帰的にマージ
      if (isPlainObject(overrideValue) && isPlainObject(defaultValue)) {
        result[key] = mergeConfig(defaultValue, overrideValue);
      } else {
        // それ以外は上書き（配列や基本型など）
        result[key] = overrideValue;
      }
    }
  }

  return result;
}
