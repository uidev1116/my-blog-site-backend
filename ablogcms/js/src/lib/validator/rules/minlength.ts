import type { ValidationRule } from '../types';

export const minlength: ValidationRule = (val, len) => {
  if (!val) {
    return true;
  }
  return parseInt(len, 10) <= String(val).length;
};
