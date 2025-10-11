import Leaflet from 'leaflet';
import icon from 'leaflet/dist/images/marker-icon.png';
import icon2x from 'leaflet/dist/images/marker-icon-2x.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import type { OpenStreetMapPickerOptions } from './open-street-map-picker';
import OpenStreetMapPicker from './open-street-map-picker';
import 'leaflet/dist/leaflet.css';

const defaultOptions: Partial<OpenStreetMapPickerOptions> = {
  searchInput: '.js-osm-search',
  searchBtn: '.js-osm-search-btn',
  lngInput: '.js-osm-lng',
  latInput: '.js-osm-lat',
  zoomInput: '.js-osm-zoom',
  msgInput: '.js-osm-msg',
  map: '.js-open-street-map-picker',
};

export default function setupOpenStreetMapPicker(
  element: HTMLElement & { openStreetMapPicker?: OpenStreetMapPicker },
  options?: Partial<OpenStreetMapPickerOptions>
): OpenStreetMapPicker {
  if (element.openStreetMapPicker) {
    return element.openStreetMapPicker;
  }

  // @ts-expect-error: Leafletの型定義の問題を回避
  delete Leaflet.Icon.Default.prototype._getIconUrl;
  Leaflet.Icon.Default.mergeOptions({
    iconUrl: icon,
    iconRetinaUrl: icon2x,
    shadowUrl: iconShadow,
  });

  const picker = new OpenStreetMapPicker(element, {
    ...defaultOptions,
    ...options,
  });

  picker.run();
  ACMS.addListener('acmsAdminDelayedContents', () => {
    picker.invalidateSize();
  });

  ACMS.addListener('onGeoInfoAdded', () => {
    picker.invalidateSize();
  });

  element.openStreetMapPicker = picker;

  return picker;
}
