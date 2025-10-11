import { type Breakpoint, type ResponsiveValue } from '../types/breakpoint';

/**
 * ブレイクポイントの値を持つオブジェクトの型
 */
export type Breakpoints = Record<Breakpoint, string>;

/**
 * CSS変数からブレイクポイントの値を取得します
 * @returns ブレイクポイントの値を持つオブジェクト
 * @throws CSS変数が取得できない場合に例外を投げます
 */
export function getBreakpoints(): Breakpoints {
  const root = document.documentElement;
  const computedStyle = getComputedStyle(root);

  const breakpointNames: Breakpoint[] = ['xs', 'sm', 'md', 'lg', 'xl'];
  const breakpoints = Object.fromEntries(
    breakpointNames.map((name) => [name, computedStyle.getPropertyValue(`--acms-admin-breakpoint-${name}`).trim()])
  ) as Breakpoints;

  // すべての値が存在するかチェック
  const missingBreakpoints = breakpointNames.filter((name) => !breakpoints[name]);
  if (missingBreakpoints.length > 0) {
    throw new Error(
      `CSS変数からブレイクポイントの値を取得できませんでした。--acms-admin-breakpoint-${missingBreakpoints.join(', ')} が定義されているか確認してください。`
    );
  }

  return breakpoints;
}

export function breakpointInfix(breakpoint: Breakpoint): string {
  return breakpoint === 'xs' ? '' : `-${breakpoint}`;
}

export function getResponsiveClasses<T extends string | number>(base: string, value?: ResponsiveValue<T>): string[] {
  if (!value) return [];
  if (typeof value !== 'object') {
    return [`${base}-${value}`];
  }
  return (Object.entries(value) as [Breakpoint, T][]).map(([bp, v]) => `${base}${breakpointInfix(bp)}-${v}`);
}
