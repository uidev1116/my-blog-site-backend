import { EmptyObject } from '../types/utils';

export function isString(value: unknown): value is string {
  return typeof value === 'string';
}

export function isNumber(value: unknown): value is number {
  return typeof value === 'number';
}

export function isObject(value: unknown): value is object {
  return typeof value === 'object' && value !== null;
}

export function isEmptyObject(value: unknown): value is EmptyObject {
  return isObject(value) && Object.keys(value).length === 0 && value.constructor === Object;
}

export function isStringArray(value: unknown): value is string[] {
  return Array.isArray(value) && value.every(isString);
}
