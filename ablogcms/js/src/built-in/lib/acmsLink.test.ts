import { afterAll, beforeAll, describe, expect, test, vi } from 'vitest';
import acmsPath from '../../lib/acmsPath/acmsPath';
import acmsLink, { AcmsLinkContext } from './acmsLink';

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
    },
  };
  globalThis.Math.random = vi.fn().mockReturnValue(0);
  globalThis.Date.now = vi.fn().mockReturnValue(0);
});

afterAll(() => {
  // @ts-expect-error グローバル変数の型エラー回避
  delete global.ACMS;
  vi.restoreAllMocks();
});

describe('acmsLink', () => {
  test('ACMS.Dispatch.revisionIndex での利用をテスト', () => {
    const context: AcmsLinkContext = {
      eid: '1',
      tpl: 'ajax/revision-index.html',
      Query: {
        hash: Math.random().toString(),
        aid: '1',
      },
    };
    expect(acmsLink(context, true)).toBe('/bid/1/cid/1/eid/1/tpl/ajax/revision-index.html?hash=0&aid=1&v=0');
  });
  test('ACMS.Dispatch.showModuleDialog での利用をテスト', () => {
    const insertContext: AcmsLinkContext = {
      tpl: 'ajax/module/edit.html',
      Query: {
        edit: 'insert',
        mid: '1',
      },
    };
    const indexContext: AcmsLinkContext = {
      tpl: 'ajax/module/list.html',
      Query: {
        edit: 'index',
        mid: '1',
        tpl: '',
      },
    };
    const updateContext: AcmsLinkContext = {
      tpl: 'ajax/module/edit.html',
      Query: {
        edit: 'update',
        mid: '1',
      },
    };
    expect(acmsLink(insertContext, true)).toBe('/bid/1/cid/1/tpl/ajax/module/edit.html?edit=insert&mid=1&v=0');
    expect(acmsLink(indexContext, true)).toBe('/bid/1/cid/1/tpl/ajax/module/list.html?edit=index&mid=1&tpl=&v=0');
    expect(acmsLink(updateContext, true)).toBe('/bid/1/cid/1/tpl/ajax/module/edit.html?edit=update&mid=1&v=0');
  });

  test('arg reference での利用をテスト', () => {
    const context: AcmsLinkContext = {
      bid: '1',
      tpl: 'ajax/arg/eid-reference.html',
      Query: {
        scope: 'eid',
        hash: Math.random().toString(),
      },
    };
    expect(acmsLink(context, false)).toBe('/bid/1/tpl/ajax/arg/eid-reference.html?scope=eid&hash=0&v=0');
  });

  test('ACMS.Dispatch.Dialog での利用をテスト', () => {
    const context: AcmsLinkContext = {
      tpl: 'ajax/dialog.html',
    };
    expect(acmsLink(context)).toBe('/bid/1/tpl/ajax/dialog.html?v=0');
  });

  test('ACMS.Dispatch.Edit._editTitle での利用をテスト', () => {
    const baseContext: AcmsLinkContext = {
      eid: '1',
      tpl: 'admin/entry/edit.html',
      admin: 'entry-edit',
      Query: {
        hash: Math.random().toString(),
      },
    };
    const actionContext: AcmsLinkContext = {
      eid: '1',
      admin: 'entry-edit',
      Query: {
        hash: Math.random().toString(),
      },
    };
    expect(acmsLink(baseContext, true)).toBe('/bid/1/admin/entry-edit/cid/1/eid/1/tpl/admin/entry/edit.html?hash=0');
    expect(acmsLink(actionContext, true)).toBe('/bid/1/admin/entry-edit/cid/1/eid/1/?hash=0');
  });

  test('ACMS.Dispatch.Edit._inplace での利用をテスト', () => {
    const addContext: AcmsLinkContext = {
      eid: '1',
      utid: '1',
      tpl: 'ajax/unit-add-single.html',
      admin: 'entry-update-unit-text',
      Query: {
        hash: Math.random().toString(),
        pos: 'below',
      },
    };
    const modifyContext: AcmsLinkContext = {
      eid: '1',
      utid: '1',
      tpl: 'ajax/unit-update-single.html',
      admin: 'entry-update-unit',
      Query: {
        hash: Math.random().toString(),
      },
    };
    const duplicatePostContext: AcmsLinkContext = {
      eid: '1',
      utid: '1',
      Query: {
        hash: Math.random().toString(),
      },
    };
    expect(acmsLink(addContext, true)).toBe(
      '/bid/1/admin/entry-update-unit-text/cid/1/eid/1/utid/1/tpl/ajax/unit-add-single.html?hash=0&pos=below&v=0'
    );
    expect(acmsLink(modifyContext, true)).toBe(
      '/bid/1/admin/entry-update-unit/cid/1/eid/1/utid/1/tpl/ajax/unit-update-single.html?hash=0&v=0'
    );
    expect(acmsLink(duplicatePostContext, true)).toBe('/bid/1/cid/1/eid/1/utid/1/?hash=0');
  });

  test('membersOnlyEntry での利用をテスト', () => {
    const context: AcmsLinkContext = {
      eid: '1',
      tpl: 'ajax/members-only-content.html',
      page: 1,
      Query: {
        eid: '1',
      },
    };
    expect(acmsLink(context)).toBe('/bid/1/eid/1/tpl/ajax/members-only-content.html?eid=1&v=0');
  });

  test('<CategorySelect /> での利用をテスト', () => {
    const context: AcmsLinkContext = {
      bid: '1',
      cid: '2',
      keyword: '物件情報',
      tpl: 'ajax/edit/category-assist.json',
      Query: {
        narrowDown: 'true',
        currentCid: '2',
      },
    };
    expect(acmsLink(context, false)).toBe(
      '/bid/1/cid/2/keyword/%E7%89%A9%E4%BB%B6%E6%83%85%E5%A0%B1/tpl/ajax/edit/category-assist.json?narrowDown=true&currentCid=2&v=0'
    );
  });

  test('<MediaAdmin /> での利用をテスト', () => {
    const mockGetTime = vi.spyOn(Date.prototype, 'getTime').mockReturnValue(1680000000000);

    const context: AcmsLinkContext = {
      tpl: 'ajax/edit/media.json',
      bid: '1',
      page: 2,
      tag: 'りんご/バナナ/みかん',
      keyword: 'バナー',
      order: 'last_modified-asc',
      limit: 50,
      date: '2024/09',
      Query: {
        type: 'all',
        ext: 'jpg',
        year: '2024',
        month: '09',
        owner: 'false',
        cache: new Date().getTime().toString(),
      },
    };
    expect(acmsLink(context, false)).toBe(
      '/bid/1/2024/09/keyword/%E3%83%90%E3%83%8A%E3%83%BC/tag/%E3%82%8A%E3%82%93%E3%81%94/%E3%83%90%E3%83%8A%E3%83%8A/%E3%81%BF%E3%81%8B%E3%82%93/page/2/order/last_modified-asc/limit/50/tpl/ajax/edit/media.json?type=all&ext=jpg&year=2024&month=09&owner=false&cache=1680000000000&v=0'
    );

    mockGetTime.mockRestore();
  });
});
