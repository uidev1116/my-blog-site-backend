function isFunction(value: unknown): value is (...args: unknown[]) => unknown {
  return typeof value === 'function';
}

function getFormElements(
  form: HTMLFormElement
): (HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement)[] {
  // フォーム内のフィールドを取得
  const elements = Array.from(form.elements).filter((element) => !(element instanceof HTMLFieldSetElement)) as (
    | HTMLInputElement
    | HTMLSelectElement
    | HTMLTextAreaElement
    | HTMLButtonElement
  )[];

  return elements;
}

export { isFunction, getFormElements };
