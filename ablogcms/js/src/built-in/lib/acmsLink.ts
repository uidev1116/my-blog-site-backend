import type { AcmsContextWithSearchParams } from '../../lib/acmsPath/types';
import { Weaken } from '../../types/utils';
import { parseUrlSearchParams } from '../../utils';

export interface AcmsLinkContext
  extends Weaken<AcmsContextWithSearchParams, 'bid' | 'cid' | 'eid' | 'uid' | 'tag' | 'date'> {
  bid?: AcmsContextWithSearchParams['bid'] | string;
  cid?: AcmsContextWithSearchParams['cid'] | string;
  eid?: AcmsContextWithSearchParams['eid'] | string;
  uid?: AcmsContextWithSearchParams['uid'] | string;
  tag?: AcmsContextWithSearchParams['tag'] | string;
  date?: AcmsContextWithSearchParams['date'] | string;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  Query?: Record<string, any>; // 互換性のため
}

interface AcmsLinkOptions {
  /**
   * 現在のURLコンテキストを継承するかどうか
   * デフォルト: false
   */
  inherit: boolean;

  /**
   * tplコンテキストがajaxから始まる場合キャッシュバスティングようのクエリを自動で付与するかどうか
   * デフォルト: true
   */
  ajaxCacheBusting: boolean;
}

const defaultOptions = {
  inherit: false,
  ajaxCacheBusting: true,
} as const satisfies AcmsLinkOptions;

/**
 * AcmsLinkContext と AcmsContext の互換性を保つための変換
 */
function toAcmsContext(context: AcmsLinkContext, options: AcmsLinkOptions): AcmsContextWithSearchParams {
  const bid = typeof context.bid === 'string' ? parseInt(context.bid, 10) : context.bid;
  const cid = typeof context.cid === 'string' ? parseInt(context.cid, 10) : context.cid;
  const eid = typeof context.eid === 'string' ? parseInt(context.eid, 10) : context.eid;
  const uid = typeof context.uid === 'string' ? parseInt(context.uid, 10) : context.uid;
  const tag = typeof context.tag === 'string' ? context.tag.split('/') : context.tag;
  let { date } = context;
  if (typeof date === 'string') {
    const [year, month, day] = date.split('/').map((v) => parseInt(v, 10));
    if (year !== undefined && !isNaN(year)) {
      date = { year };
      if (month !== undefined && !isNaN(month)) {
        date.month = month;
        if (day !== undefined && !isNaN(day)) {
          date.day = day;
        }
      }
    } else {
      date = undefined;
    }
  }
  // 互換性を考慮して、Query でも searchParams を指定できるようにする
  const _searchParams = context.searchParams || context.Query;
  const { Query: _, ...inheritContext } = context; // eslint-disable-line @typescript-eslint/no-unused-vars
  let searchParams = _searchParams instanceof URLSearchParams ? parseUrlSearchParams(_searchParams) : _searchParams;
  if (options.ajaxCacheBusting && context.tpl && /^ajax\//.test(context.tpl)) {
    searchParams = {
      ...searchParams,
      v: Date.now().toString(),
    };
  }
  return {
    ...inheritContext,
    bid,
    cid,
    eid,
    uid,
    tag,
    date,
    searchParams,
  };
}

/**
 * acmsLink
 */
export default function acmsLink(context: AcmsLinkContext, options: boolean | Partial<AcmsLinkOptions> = {}) {
  const _options: Partial<AcmsLinkOptions> = typeof options === 'boolean' ? { inherit: options } : options;
  const config: AcmsLinkOptions = { ...defaultOptions, ..._options };
  const { Config } = ACMS;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  function empty(value: any) {
    return typeof value === 'undefined' || value === null;
  }

  //-----------------
  // inherit context
  if (config.inherit) {
    if (empty(context.cid)) {
      context.cid = Config.cid;
    }
    if (empty(context.eid)) {
      context.eid = Config.eid;
    }
    if (empty(context.admin)) {
      context.admin = Config.admin;
    }
    if (empty(context.keyword)) {
      context.keyword = Config.keyword;
    }
  }

  if (empty(context.bid) || context.bid === '') {
    context.bid = Config.bid;
  }

  let url = Config.scriptRoot;
  url += ACMS.Library.acmsPath(toAcmsContext(context, config));

  return url;
}
