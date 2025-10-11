import type { ValidationRule } from '../types';

export const min: ValidationRule = (val, num) => {
  if (!val) {
    return true;
  }
  return parseInt(num, 10) <= parseInt(val, 10);
};
