export function capitalize<T extends string>(str: T): Capitalize<T> {
  return `${str.charAt(0).toUpperCase()}${str.substring(1)}` as Capitalize<T>;
}

const newlineRegex = /(\r\n|\r|\n)/g;

export function nl2br(string: string) {
  if (typeof string !== 'string') {
    return string;
  }

  return string
    .split(newlineRegex)
    .map((line) => {
      if (line.match(newlineRegex)) {
        return '<br />';
      }
      return line;
    })
    .join('');
}

export function stringLength(string: string): number {
  const segmenter = new Intl.Segmenter(navigator.languages as string[], { granularity: 'grapheme' });
  return [...segmenter.segment(string)].length;
}

export function truncate(text: string, length: number, ellipsis = '...'): string {
  if (stringLength(text) <= length) {
    return text;
  }

  return `${text.slice(0, length - stringLength(ellipsis))}${ellipsis}`;
}

export function truncateUrl(url: string, maxLength: number): string {
  if (stringLength(url) <= maxLength) {
    return url;
  }

  const partLength = Math.floor((maxLength - 3) / 2);
  const start = url.slice(0, partLength);
  const end = url.slice(-partLength);

  return `${start}...${end}`;
}

export function stripHtmlTags(html: string): string {
  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    return doc.body.textContent?.trim() || '';
  } catch (error) {
    console.error('Error parsing HTML string:', error); // eslint-disable-line no-console
    return '';
  }
}

/**
 * kebab-case や snake_case を camelCase に変換
 */
export function toCamelCase(str: string): string {
  return str.replace(/[-_](\w)/g, (_, c) => c.toUpperCase());
}

/**
 * 文字列の最後に出てくる指定の部分文字列を、置き換える関数
 * @param input 元の文字列
 * @param search 置き換えたい最後に出てくる部分文字列
 * @param replacement 置き換え後の文字列
 * @returns 置き換え後の文字列
 */
export function replaceLast(input: string, search: string, replacement: string): string {
  const lastIndex = input.lastIndexOf(search);

  if (lastIndex === -1) {
    // 指定の文字列が含まれていない場合は元の文字列をそのまま返す
    return input;
  }

  const newString = input.substring(0, lastIndex) + replacement + input.substring(lastIndex + search.length);
  return newString;
}
