import { parseFormData } from 'parse-nested-form-data';
import AcmsFieldList from '../../lib/acmsPath/acmsField';
import { AcmsContext } from '../../lib/acmsPath/types';

interface ParsedObject {
  bid?: string;
  cid?: string;
  eid?: string;
  uid?: string;
  utid?: string;
  tag?: string[];
  start?: string;
  span?: string;
  end?: string;
  date?: string | string[];
  page?: string;
  order?: string;
  limit?: string;
  keyword?: string;
  admin?: string;
  tpl?: string;
  api?: string;
  query?: string[];
  field?: string[];
  [key: string]: any; // eslint-disable-line @typescript-eslint/no-explicit-any
}

export default function createAcmsContextFromFormData(formData: FormData): {
  context: AcmsContext;
  searchParams: URLSearchParams;
} {
  const data = parseFormData(formData) as ParsedObject;
  const { query, start = '1000-01-01 00:00:00', span: _span, end = '9999-12-31 23:59:59', date: _date, ...rest } = data;

  const searchParams = new URLSearchParams();
  query?.forEach((key) => {
    if (key in rest) {
      const value = rest[key];
      if (Array.isArray(value)) {
        value.forEach((v) => {
          searchParams.append(key, v);
        });
      } else {
        searchParams.append(key, value);
      }
      delete rest[key];
    }
  });

  let span;
  if (_span != null && _span !== '') {
    span = { start, end };
  }

  let date;
  if (_date != null && _date !== '') {
    date = Array.isArray(_date)
      ? { year: Number(_date[0]), month: Number(_date[1]), day: Number(_date[2]) }
      : _date.split('/').reduce(
          (acc, v, i) => {
            acc[['year', 'month', 'day'][i] as 'year' | 'month' | 'day'] = Number(v);
            return acc;
          },
          {} as { year: number; month?: number; day?: number }
        );
  }

  const context: AcmsContext = {
    bid: rest.bid ? Number(rest.bid) : undefined,
    cid: rest.cid ? Number(rest.cid) : undefined,
    eid: rest.eid ? Number(rest.eid) : undefined,
    uid: rest.uid ? Number(rest.uid) : undefined,
    utid: rest.utid,
    tag: rest.tag,
    span,
    date,
    page: rest.page ? Number(rest.page) : undefined,
    order: rest.order,
    limit: rest.limit ? Number(rest.limit) : undefined,
    keyword: rest.keyword,
    admin: rest.admin,
    tpl: rest.tpl,
    api: rest.api,
    field: AcmsFieldList.fromFormData(formData),
  };

  return { context, searchParams };
}
