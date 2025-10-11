import type { GoogleMapsPickerOptions } from './google-maps-picker';
import GoogleMapsPicker from './google-maps-picker';

export default function setupGoogleMapsPicker(
  element: HTMLElement & { googleMapsPicker?: GoogleMapsPicker },
  options: Partial<GoogleMapsPickerOptions> = {}
): GoogleMapsPicker {
  if (element.googleMapsPicker) {
    return element.googleMapsPicker;
  }

  const picker = new GoogleMapsPicker(element, options);

  picker.init();

  element.googleMapsPicker = picker;

  return picker;
}
