import type { ValidationRule } from '../types';

export const allMinChecked: ValidationRule = (_, num, checkboxes) => {
  if (!Array.isArray(checkboxes)) {
    return true;
  }
  if (!checkboxes.every((checkbox) => checkbox instanceof HTMLInputElement)) {
    return true;
  }
  return parseInt(num, 10) <= checkboxes.filter(({ checked }) => checked).length;
};
