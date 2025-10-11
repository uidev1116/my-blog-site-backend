/**
 * カスタムフィールド用のライトエディタを初期化する
 * @deprecated LiteEditor は非推奨です
 * @param {Element | Document} context
 * @returns {void}
 */
const dispatchLiteEditorField = async (context = document) => {
  const elements = context.querySelectorAll(ACMS.Config.LiteEditorMark);
  if (elements.length === 0) {
    return;
  }
  const { default: setupLiteEditorField } = await import(
    /* webpackChunkName: "lite-editor" */ '../lib/lite-editor/field'
  );
  elements.forEach((element) => {
    setupLiteEditorField(element);
  });
};

export default dispatchLiteEditorField;
