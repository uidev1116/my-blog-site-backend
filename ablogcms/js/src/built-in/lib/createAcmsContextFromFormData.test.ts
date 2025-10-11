import { describe, it, expect, beforeAll } from 'vitest';
import createAcmsContextFromFormData from './createAcmsContextFromFormData';
import acmsLink from './acmsLink';
import acmsPath from '../../lib/acmsPath/acmsPath';
import { parseUrlSearchParams } from '../../utils';

beforeAll(() => {
  // @ts-expect-error グローバル変数の型エラー回避
  global.ACMS = {
    Config: {
      bid: '1',
      cid: '1',
      admin: '',
      keyword: '',
      scriptRoot: '/',
    },
    Library: {
      acmsPath,
      acmsLink,
    },
  };
});

describe('createAcmsContextFromFormData', () => {
  it('should create a URL with search parameters from formData', () => {
    const formData = new FormData();
    formData.append('query[]', 'keyword');
    formData.append('query[]', 'page');
    formData.append('keyword', 'test');
    formData.append('page', '2');

    const result = createAcmsContextFromFormData(formData);
    expect(parseUrlSearchParams(result.searchParams)).toStrictEqual({ keyword: 'test', page: '2' });
  });

  it('should handle multiple values for the same query key', () => {
    const formData = new FormData();
    formData.append('query[]', 'value');
    formData.append('value[]', 'hoge');
    formData.append('value[]', 'fuga');

    const result = createAcmsContextFromFormData(formData);
    expect(parseUrlSearchParams(result.searchParams)).toStrictEqual({ value: ['hoge', 'fuga'] });
  });

  it('should correctly handle span and date', () => {
    const formData = new FormData();
    formData.append('span', 'span');
    formData.append('start', '2023-01-01 00:00:00');
    formData.append('end', '2023-12-31 23:59:59');

    const result = createAcmsContextFromFormData(formData);
    expect(result.context.span).toStrictEqual({ start: '2023-01-01 00:00:00', end: '2023-12-31 23:59:59' });
  });

  it('should correctly handle date', () => {
    const formData = new FormData();
    formData.append('date[]', '2024');
    formData.append('date[]', '10');
    formData.append('date[]', '23');

    const result = createAcmsContextFromFormData(formData);
    expect(result.context.date).toStrictEqual({ year: 2024, month: 10, day: 23 });
  });

  it('should correctly handle field values using AcmsFieldList.fromFormData', () => {
    const formData = new FormData();
    formData.append('field[]', 'title');
    formData.append('title@operator[]', 'eq');
    formData.append('title@connector[]', 'and');
    formData.append('title[]', 'test');

    const result = createAcmsContextFromFormData(formData);
    expect(result.context.field?.getFields()).toStrictEqual([
      {
        key: 'title',
        filters: [
          {
            operator: 'eq',
            connector: 'and',
            value: 'test',
          },
        ],
        separator: '_and_',
      },
    ]);
  });
});
