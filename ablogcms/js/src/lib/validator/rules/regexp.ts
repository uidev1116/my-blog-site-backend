import type { ValidationRule } from '../types';
import { regex } from './regex';

export const regexp: ValidationRule = (val, pattern, input, v) => {
  return regex(val, pattern, input, v);
};
