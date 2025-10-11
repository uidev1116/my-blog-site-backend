import type { ValidationRule } from '../types';

export const filesize: ValidationRule = (_, max, input) => {
  if (!(input instanceof HTMLInputElement)) {
    return true;
  }
  if (!input.files) {
    return true;
  }
  if (input.files.length < 1) {
    return true;
  }
  if (input.files[0].size > parseInt(max, 10) * 1024) {
    return false;
  }
  return true;
};
