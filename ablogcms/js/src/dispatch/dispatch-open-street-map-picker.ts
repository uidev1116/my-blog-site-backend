import type OpenStreetMapPicker from '../lib/open-street-map/picker/open-street-map-picker';

export default async function dispatchOpenStreetMapPicker(
  context: Document | Element = document
): Promise<OpenStreetMapPicker[]> {
  const elements = context.querySelectorAll<HTMLElement>('.js-open-street-map-editable');
  if (elements.length === 0) {
    return [];
  }
  const { default: setupOpenStreetMapPicker } = await import(
    /* webpackChunkName: "open-street-map-picker" */ '../lib/open-street-map/picker'
  );
  return Array.from(elements).map((element) => {
    const picker = setupOpenStreetMapPicker(element);
    return picker;
  });
}
