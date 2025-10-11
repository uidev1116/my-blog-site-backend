import Expand from 'ui-expand';

export default function setupExpand(context: Document | Element) {
  /**
   * Expand SmartBlock
   * 1つの要素に対して重複して実行された場合、拡大と縮小が同時に動作してしまう問題対策で
   * ui-expand-initializedクラスを付与 & カスタムフィールドグループのテンプレート要素内の要素は除外
   */
  const { fieldgroupSortableItemTemplateMark } = ACMS.Config;
  const expands = context.querySelectorAll<HTMLElement>(
    `.js-expand:not(.ui-expand-initialized):not(${fieldgroupSortableItemTemplateMark} .js-expand)`
  );
  // eslint-disable-next-line no-new
  const expand = new Expand(expands, {
    beforeOpen: (element) => {
      element?.classList.add('js-acms-expanding');
      element?.closest<HTMLElement>('.js-visible-on-ui-expanding')?.style.setProperty('overflow', 'visible');
    },
    onOpen: (element) => {
      element?.querySelectorAll<HTMLElement>('.js-expand-icon').forEach((icon) => {
        icon.classList.remove('acms-admin-icon-expand-arrow');
        icon.classList.add('acms-admin-icon-contract-arrow');
      });
      element?.classList.add('js-acms-expanded');
    },
    beforeClose: (element) => {
      element?.closest<HTMLElement>('.js-visible-on-ui-expanding')?.style.removeProperty('overflow');
    },
    onClose: (element) => {
      element?.classList.remove('js-acms-expanding');
      element?.classList.remove('js-acms-expanded');
      element?.querySelectorAll<HTMLElement>('.js-expand-icon').forEach((icon) => {
        icon.classList.add('acms-admin-icon-expand-arrow');
        icon.classList.remove('acms-admin-icon-contract-arrow');
      });
    },
  });
  expands.forEach((expand) => {
    expand.classList.add('ui-expand-initialized');
  });

  return expand;
}
