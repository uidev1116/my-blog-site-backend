import type { ValidationRule } from '../types';

export const katakana: ValidationRule = (val) => {
  if (!val) {
    return true;
  }
  if (val.match(/^[ァ-ヾー]+$/)) {
    return true;
  }
  return false;
};
