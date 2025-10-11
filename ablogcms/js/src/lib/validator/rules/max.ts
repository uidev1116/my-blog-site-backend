import type { ValidationRule } from '../types';

export const max: ValidationRule = (val, num) => {
  if (!val) {
    return true;
  }
  return parseInt(num, 10) >= parseInt(val, 10);
};
