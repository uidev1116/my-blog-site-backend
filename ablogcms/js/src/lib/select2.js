import 'select2/dist/js/select2.full';
import 'select2/dist/css/select2.css';

const defaultOption = {
  containerCssClass: 'acms-admin-selectbox',
  dropdownCssClass: 'acms-admin-select-dropdown',
};

export default function setupSelect2(element, option = {}) {
  const options = { ...defaultOption, ...option };
  const $element = $(element);
  $element
    .select2(options)
    .on('select2:open', () => {
      const positionY = element.getBoundingClientRect().top;
      let margin = window.innerHeight - positionY;
      if (positionY > margin) {
        margin = positionY;
      }
      margin -= 150;
      if (margin < 200) {
        margin = 200;
      }
      $('.select2-results__options').css('max-height', `${margin}px`);
    })
    .on('select2:select', () => {
      $(element).trigger('change');
      element.dispatchEvent(new Event('change', { bubbles: true }));
    });
  if (element.closest('.acms-admin-modal-dialog')) {
    $(element).data('select2').$dropdown.addClass('select2-in-modal');
  }
}
