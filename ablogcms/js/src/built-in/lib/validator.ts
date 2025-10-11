import { ValidatorOptions } from '../../lib/validator/types';

async function validator(form: HTMLFormElement, options: Partial<ValidatorOptions> = {}) {
  const { default: Validator } = await import(/* webpackChunkName: "validator" */ '../../lib/validator');
  return new Validator(form, options);
}

export default validator;
