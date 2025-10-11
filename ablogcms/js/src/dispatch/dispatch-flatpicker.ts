export default async function dispatchFlatpicker(context: Element | Document) {
  const datepickerElements = context.querySelectorAll(ACMS.Config.flatDatePicker);
  if (datepickerElements && datepickerElements.length) {
    const { default: setupDatePicker } = await import(
      /* webpackChunkName: "setup-flat-date-picker" */ '../lib/flatpicker/date-picker'
    );
    [].forEach.call(datepickerElements, (element) => {
      setupDatePicker(element);
    });
  }

  //-------------------
  // timepicker
  const timepickerElements = context.querySelectorAll(ACMS.Config.flatTimePicker);
  if (timepickerElements && timepickerElements.length) {
    const { default: setupTimePicker } = await import(
      /* webpackChunkName: "setup-flat-time-picker" */ '../lib/flatpicker/time-picker'
    );
    [].forEach.call(timepickerElements, (element) => {
      setupTimePicker(element);
    });
  }
}
