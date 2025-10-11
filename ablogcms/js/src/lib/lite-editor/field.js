import LiteEditor from 'lite-editor';
import 'lite-editor/css/lite-editor.css';

/**
 * カスタムフィールド用のライトエディタを初期化する
 * @deprecated LiteEditor は非推奨です
 * @param {HTMLElement} element
 * @param {Object} options
 * @returns
 */
export default function setupLiteEditorField(element, options = {}) {
  if (element.closest(ACMS.Config.fieldgroupSortableItemTemplateMark)) {
    // フィールドグループのテンプレート要素の場合はスキップ
    return;
  }
  if (element.LiteEditor instanceof LiteEditor) {
    return;
  }
  const liteEditor = new LiteEditor(element, { ...ACMS.Config.LiteEditorFieldConf, ...options });
  element.LiteEditor = liteEditor;
  return liteEditor;
}
