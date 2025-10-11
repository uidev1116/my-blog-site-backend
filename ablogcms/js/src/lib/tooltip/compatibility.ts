function convertPosition(position?: string | null): string | undefined {
  switch (position) {
    case 'right':
      return 'right';
    case 'left':
      return 'left';
    case 'bottom':
      return 'bottom';
    case 'top':
      return 'top';
    case 'top-left':
      return 'top-start';
    case 'top-right':
      return 'top-end';
    case 'bottom-left':
      return 'bottom-start';
    case 'bottom-right':
      return 'bottom-end';
    default:
      return 'top';
  }
}

/**
 * ツールチップ要素をReactTooltipの形式に変換
 * 旧ツールチップとの互換性を保つために使用
 * @param element - 変換対象のHTMLElement
 */
export const fixTooltipAttribute = (element: HTMLElement, tooltipId: string) => {
  if (!element.hasAttribute('data-tooltip-id')) {
    element.setAttribute('data-tooltip-id', tooltipId);

    const html = element.getAttribute('data-acms-tooltip') || 'ここにヘルプが入ります。';
    element.setAttribute('data-tooltip-html', html);
    element.removeAttribute('data-acms-tooltip');

    const pos = convertPosition(element.getAttribute('data-acms-position'));
    if (pos) {
      element.setAttribute('data-tooltip-place', pos);
      element.removeAttribute('data-acms-position');
    }
  }
};
