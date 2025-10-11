declare module 'nestify' {
  interface NestifyConfig {
    id: string;
    parentId: string;
    children: string;
  }

  export function nestify<T>(config: NestifyConfig, items: T[]): (T & { [key: string]: any })[]; // eslint-disable-line @typescript-eslint/no-explicit-any

  export function flatify<T>(config: NestifyConfig, items: (T & { [key: string]: any })[]): T[]; // eslint-disable-line @typescript-eslint/no-explicit-any
}
