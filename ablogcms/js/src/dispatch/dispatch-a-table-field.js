/**
 * a-table-field の dispatch 関数
 * @param {Element | Document} context コンテキスト
 * @returns {void}
 */
const dispatchAtableField = async (context = document) => {
  if (!context) {
    return;
  }
  const elements = context.querySelectorAll(ACMS.Config.aTableFieldMark);
  if (elements.length === 0) {
    return;
  }
  const { default: setupAtable } = await import(/* webpackChunkName: "a-table" */ '../lib/a-table');
  elements.forEach((element) => {
    setupAtable(element);
  });
};

export default dispatchAtableField;
