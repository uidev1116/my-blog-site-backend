import flatPicker from 'flatpickr';
import 'flatpickr/dist/l10n/ja';
import 'flatpickr/dist/flatpickr.min.css';

export default function setupDatePicker(
  element: HTMLInputElement & { flatPicker?: flatPicker.Instance },
  options: flatPicker.Options.Options = {}
) {
  if (element.flatPicker !== undefined) {
    return element.flatPicker;
  }
  const defaultOptions: flatPicker.Options.Options = {
    ...ACMS.Config.flatTimePickerConfig,
    locale: /^ja/.test(ACMS.i18n.lng) ? 'ja' : undefined,
    defaultDate: element.value,
  };
  const picker = flatPicker(element, { ...defaultOptions, ...options });
  element.setAttribute('autocomplete', 'off');
  element.addEventListener('change', (event: Event) => {
    if (event.target instanceof HTMLInputElement) {
      picker.jumpToDate(event.target.value);
      picker.setDate(event.target.value);
    }
  });
  element.flatPicker = picker;

  return picker;
}
