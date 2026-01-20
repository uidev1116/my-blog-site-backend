/* eslint no-bitwise: "off" */

/**
 * HEXカラーコードからコントラスト比に応じたテキストカラーを取得する
 * @param hex HEXカラーコード（例: '#35353a'）
 * @param black 暗い背景色用のテキストカラー（デフォルト: '#000000'）
 * @param white 明るい背景色用のテキストカラー（デフォルト: '#ffffff'）
 * @returns コントラスト比に応じたテキストカラー
 */
export const contrastColor = (hex: string, black = '#000000', white = '#ffffff') => {
  const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);

  if (rgb === null) {
    return white;
  }
  const a = 1 - (0.299 * parseInt(rgb[1], 16) + 0.587 * parseInt(rgb[2], 16) + 0.114 * parseInt(rgb[3], 16)) / 255;

  if (a < 0.4) {
    // bright colors - black font
    return black;
  }
  // dark colors - white font
  return white;
};

/**
 * RGB文字列をHEXカラーコードに変換する
 * @param rgb RGB文字列（例: 'rgb(255, 0, 0)'）
 * @returns HEXカラーコード（例: '#ff0000'）
 */
export const rgb2hex = (rgb: string) => {
  const rgbAry = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
  const hexDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
  if (!rgbAry) {
    return '';
  }
  return `#${[rgbAry[1], rgbAry[2], rgbAry[3]]
    // @ts-expect-error typescript のisNaNの引数はnumber型のみだが、JavaScriptのisNaNはstring型を受け付ける
    .map((x) => (isNaN(x) ? '00' : hexDigits[(x - (x % 16)) / 16] + hexDigits[x % 16]))
    .join('')}`;
};

/**
 * #RRGGBB 同士を線形補間して #RRGGBB を返す
 * @param color1 開始色（HEXカラーコード）
 * @param color2 終了色（HEXカラーコード）
 * @param weight 補間係数（0.0 から 1.0 の間）
 * @returns 補間されたHEXカラーコード
 */
export const mix = (color1: string, color2: string, weight: number): string => {
  const pa = parseInt(color1.replace('#', ''), 16);
  const pb = parseInt(color2.replace('#', ''), 16);
  const ar = (pa >> 16) & 255;
  const ag = (pa >> 8) & 255;
  const ab = pa & 255;
  const br = (pb >> 16) & 255;
  const bg = (pb >> 8) & 255;
  const bb = pb & 255;
  const r = Math.round(ar + (br - ar) * weight);
  const g = Math.round(ag + (bg - ag) * weight);
  const b = Math.round(ab + (bb - ab) * weight);
  return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
};
