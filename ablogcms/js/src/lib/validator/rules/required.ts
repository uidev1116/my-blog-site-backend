import type { ValidationRule } from '../types';
import { getFormElements } from '../utils';

export const required: ValidationRule = (val, _, input) => {
  if (Array.isArray(input)) {
    return true;
  }
  if (input.type === 'checkbox' || input.type === 'radio') {
    if (input.form === null) {
      return true;
    }
    const inputs = getFormElements(input.form)
      .filter((element) => element.name === input.name)
      .filter((element) => !(element as HTMLInputElement).disabled);
    return inputs.some((input) => (input as HTMLInputElement).checked === true);
  }

  return !!val;
};
