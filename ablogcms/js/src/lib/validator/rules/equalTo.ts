import type { ValidationRule } from '../types';
import { getFormElements } from '../utils';

export const equalTo: ValidationRule = (val, name, input) => {
  if (Array.isArray(input)) {
    return true;
  }

  if (input.form === null) {
    return true;
  }

  const input2 = getFormElements(input.form).find((element) => element.name === name);

  if (input2 === undefined) {
    // バリデーション対象の要素がフォームの要素ではない場合は無効なルールとしてtrueを返す
    return true;
  }

  if (
    !(input2 instanceof HTMLInputElement) &&
    !(input2 instanceof HTMLSelectElement) &&
    !(input2 instanceof HTMLTextAreaElement) &&
    !(input2 instanceof HTMLButtonElement)
  ) {
    // バリデーション対象の要素がフォームの要素ではない場合は無効なルールとしてtrueを返す
    return true;
  }

  return val === input2.value;
};
