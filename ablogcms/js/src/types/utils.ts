export type RecursivePartial<T> = {
  [P in keyof T]?: T[P] extends Array<infer U>
    ? Array<RecursivePartial<U>>
    : T[P] extends object
      ? RecursivePartial<T[P]>
      : T[P];
};

/**
 * Weaken the type of the specified keys.
 */
export type Weaken<T, K extends keyof T> = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  [P in keyof T]: P extends K ? any : T[P];
};

export type EmptyObject = Record<string, never>;

export type ValueOf<T> = T[keyof T];

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Dict<T = any> = Record<string, T>;
