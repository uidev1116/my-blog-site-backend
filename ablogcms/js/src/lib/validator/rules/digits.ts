import type { ValidationRule } from '../types';

export const digits: ValidationRule = (val) => {
  if (!val) {
    return true;
  }
  return val === String(parseInt(val, 10));
};
