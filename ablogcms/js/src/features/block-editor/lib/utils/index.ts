import classnames, { ArgumentArray as ClassValues } from 'classnames';

export function cn(...inputs: ClassValues) {
  return classnames(...inputs);
}

// eslint-disable-next-line
export function randomElement(array: any[]) {
  return array[Math.floor(Math.random() * array.length)];
}

export * from './isCustomNodeSelected';
export * from './isTextSelected';
