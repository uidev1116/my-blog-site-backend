export default async function dispatchGoogleMapsPicker(context: Document | HTMLElement = document) {
  const elements = context.querySelectorAll<HTMLElement>('.js-map-editable');

  if (elements.length === 0) {
    return;
  }

  const { default: setup } = await import(/* webpackChunkName: "google-maps-picker" */ '../lib/google-maps/picker');

  elements.forEach((element) => {
    if (element.closest(ACMS.Config.fieldgroupSortableItemTemplateMark)) {
      // フィールドグループのテンプレート内の要素は初期化しない
      return;
    }
    setup(element);
  });
}
