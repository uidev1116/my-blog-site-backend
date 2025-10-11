import type { ValidationRule } from '../types';

export const hiragana: ValidationRule = (val) => {
  if (!val) {
    return true;
  }
  if (val.match(/^[ぁ-ゞー]+$/)) {
    return true;
  }
  return false;
};
