/**
 * CSS変数から値を取得する
 * @param variableName CSS変数名（例: '--acms-admin-group-unit-text-light'）
 * @param fallback フォールバック値（CSS変数が取得できない場合）
 * @param element 取得元の要素（指定しない場合はdocument.documentElement）
 * @returns CSS変数の値またはフォールバック値
 */
export const getCSSVariable = (variableName: string, fallback?: string, element?: Element | null): string => {
  if (typeof window === 'undefined') {
    return fallback || '';
  }

  const targetElement = element || document.documentElement;
  const computedStyle = getComputedStyle(targetElement);
  const value = computedStyle.getPropertyValue(variableName).trim();

  return value || fallback || '';
};
