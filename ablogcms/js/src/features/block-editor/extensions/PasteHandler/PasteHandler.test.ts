import { describe, it, expect, vi, beforeAll, afterAll, beforeEach, afterEach } from 'vitest';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { DOMParser as PMDOMParser, Slice } from '@tiptap/pm/model';
import { EditorView } from '@tiptap/pm/view';

import { fetchClient } from '../../../../lib/fetch-client';
import { pastePlugin, PasteHandler } from './PasteHandler';
import { ImageBlock } from '../ImageBlock';
import { FileBlock } from '../FileBlock';

// 循環依存を断ち切るため各 View をモック
vi.mock('../ImageBlock/components/ImageBlockView', () => ({
  ImageBlockView: vi.fn(),
}));
vi.mock('../FileBlock/components/FileBlockView', () => ({
  FileBlockView: vi.fn(),
}));

// fetchClient をモック（fetchMediaItem 内で使用）
vi.mock('../../../../lib/fetch-client', () => ({
  fetchClient: { get: vi.fn() },
}));

// グローバルモック設定
beforeAll(() => {
  (
    global as unknown as {
      ACMS: {
        Config: { root: string; eid: number; bid: string };
        Library: { acmsLink: ReturnType<typeof vi.fn> };
      };
    }
  ).ACMS = {
    Config: { root: '/', eid: 1, bid: '1' },
    Library: {
      acmsLink: vi.fn(() => 'http://localhost/mock-media-api'),
    },
  };
  vi.stubGlobal('location', { origin: 'http://localhost' });
});

afterAll(() => {
  vi.unstubAllGlobals();
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.mocked(fetchClient.get).mockReset();
});

// モック用FetchResponseヘルパー
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function mockResponse(data: any) {
  return { data, status: 200, statusText: 'OK', headers: new Headers() };
}

// テスト用最小構成Editorを生成
function createTestEditor() {
  return new Editor({
    element: document.createElement('div'),
    extensions: [StarterKit, ImageBlock, FileBlock, PasteHandler],
  });
}

// ClipboardEventモックを生成
function makeClipboardEvent(html: string): ClipboardEvent {
  return {
    clipboardData: {
      getData: (type: string) => (type === 'text/html' ? html : ''),
    },
  } as unknown as ClipboardEvent;
}

// EditorViewモックを生成
function makeEditorView() {
  const mockTr = { replaceSelection: vi.fn().mockReturnThis() };
  const dispatch = vi.fn();
  const view = {
    state: { tr: mockTr },
    dispatch,
  } as unknown as EditorView;
  return { view, dispatch };
}

// PMDOMParser.fromSchema をスパイして parseSlice に渡される body を捕捉するヘルパー。
// vi.spyOn を使用しているため afterEach の vi.restoreAllMocks() で自動リストアされる。
// テスト本体内で呼び出すこと。
function spyOnParseSlice(): { getCapturedBody: () => Element | null } {
  let capturedBody: Element | null = null;
  const originalFromSchema = PMDOMParser.fromSchema.bind(PMDOMParser);
  vi.spyOn(PMDOMParser, 'fromSchema').mockImplementation((schema) => {
    const parser = originalFromSchema(schema);
    const originalParseSlice = parser.parseSlice.bind(parser);
    parser.parseSlice = (dom, options) => {
      capturedBody = dom as Element;
      return originalParseSlice(dom, options);
    };
    return parser;
  });
  return { getCapturedBody: () => capturedBody };
}

// fileBlock ペースト用HTMLを生成するヘルパー
function makeFileBlockHtml({ mid, href }: { mid?: string; href: string }) {
  const midAttr = mid ? ` data-mid="${mid}"` : '';
  return `<div class="media-file-block align-left" data-type="fileBlock"${midAttr} data-href="${href}" data-display-type="icon">
    <div><a href="${href}"><img src="/icon.png" width="100" height="100"></a></div>
  </div>`;
}

describe('pastePlugin', () => {
  let editor: Editor;

  beforeEach(() => {
    editor = createTestEditor();
  });

  afterEach(() => {
    editor.destroy();
  });

  // handlePaste の第3引数 slice はこのプラグインでは使用しないため null を渡す
  function callHandlePaste(view: EditorView, event: ClipboardEvent) {
    const plugin = pastePlugin(editor);
    const fn = plugin.props.handlePaste as (view: EditorView, event: ClipboardEvent, slice: Slice) => boolean | void;
    return fn(view, event, null as unknown as Slice);
  }

  // ─────────────────────────────────────────────
  // グループ1: handlePaste の戻り値
  // ─────────────────────────────────────────────
  describe('handlePaste の戻り値', () => {
    it('HTMLなし（テキストのみ）→ false を返す', () => {
      const { view } = makeEditorView();
      const event = makeClipboardEvent('');
      expect(callHandlePaste(view, event)).toBe(false);
    });

    it('HTMLあり・<img>も fileBlock もなし → false を返す', () => {
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<p>Hello</p>');
      expect(callHandlePaste(view, event)).toBe(false);
    });

    it('HTMLあり・<img>あり → true を返す', () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      expect(callHandlePaste(view, event)).toBe(true);
    });

    it('HTMLあり・fileBlock あり → true を返す', () => {
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/file.pdf', media_root_path: '/file.pdf' } })
      );
      const { view } = makeEditorView();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '1', href: '/file.pdf' }));
      expect(callHandlePaste(view, event)).toBe(true);
    });
  });

  // ─────────────────────────────────────────────
  // グループ2: 画像URLの変換
  // ─────────────────────────────────────────────
  describe('画像URLの変換', () => {
    it('同一オリジン画像かつ、サーバー上に画像ファイルが存在する → 相対パスに変換される', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
    });

    it('同一オリジン画像かつ、サーバー上に画像ファイルが存在しない → src が変換されない（絶対URLのまま）', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      callHandlePaste(view, event);
      // 仕様: HEAD 結果に関わらず Promise.all().then() で dispatch は必ず呼ばれる
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('src')).toBe('http://localhost/img.jpg');
    });

    it('クエリ付きURLかつ、サーバー上に画像ファイルが存在する → クエリが除去された相対パスに変換される', async () => {
      const fetchMock = vi.fn().mockResolvedValue({ ok: true });
      vi.stubGlobal('fetch', fetchMock);
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg?v=123&w=200">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(fetchMock).toHaveBeenCalledWith('http://localhost/img.jpg', { method: 'HEAD' });
    });

    it('相対パスsrc → HEADはスキップされ console.warn なし', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="/relative/path.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(warnSpy).not.toHaveBeenCalled();
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('data: / blob: プロトコル → スキップされ console.warn なし', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="data:image/png;base64,abc"><img src="blob:http://localhost/uuid">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(warnSpy).not.toHaveBeenCalled();
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('本当に無効なURL → console.warn が呼ばれ、変換されない', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      vi.stubGlobal('fetch', vi.fn());
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(warnSpy).toHaveBeenCalledWith('ペースト画像のURL変換処理に失敗しました:', expect.any(Error));
    });

    it('fetchが例外をスロー → console.warn が呼ばれ、変換されない', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network error')));
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(warnSpy).toHaveBeenCalledWith('ペースト画像のURL変換処理に失敗しました:', expect.any(Error));
    });
  });

  // ─────────────────────────────────────────────
  // グループ3: 画像 data-mid の検証
  // ─────────────────────────────────────────────
  describe('画像 data-mid の検証', () => {
    it('data-mid付き・APIが status:success かつ media_root_path が一致 → data-mid保持（同一CMSのメディア）', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/img.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('data-mid')).toBe('42');
    });

    it('data-mid付き・APIが status:failure を返す → data-mid除去', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'failure', message: 'Media not found.' })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="http://other-cms.com/img.jpg" data-mid="99">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('data-mid')).toBeNull();
    });

    it('data-mid付き・APIが例外をスロー → data-mid除去（安全のため）', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockRejectedValueOnce(new Error('network error'));
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('data-mid')).toBeNull();
    });

    it('data-midなし → media APIは呼ばれない', async () => {
      const fetchMock = vi.fn().mockResolvedValue({ ok: true });
      vi.stubGlobal('fetch', fetchMock);
      const { view } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(fetchMock).toHaveBeenCalledWith('http://localhost/img.jpg', { method: 'HEAD' });
      expect(vi.mocked(fetchClient.get)).not.toHaveBeenCalled();
    });

    it('相対パスsrc + data-mid付き → APIの media_root_path と一致 → data-mid保持', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/relative/img.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="/relative/img.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      const img = getCapturedBody()?.querySelector('img');
      expect(img?.getAttribute('src')).toBe('/relative/img.jpg');
      expect(img?.getAttribute('data-mid')).toBe('42');
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('クエリ付きsrc + data-mid付き → クエリを除去して media_root_path と比較 → 一致すれば data-mid保持', async () => {
      const fetchMock = vi.fn().mockResolvedValue({ ok: true });
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/img.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg?v=123" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('data-mid')).toBe('42');
    });

    it('リサイズ済みsrc（mode1_w200-original.jpg）+ data-mid付き → オリジナルと一致 → data-mid保持・srcがmedia_root_pathに書き換わる', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/uploads/original.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="/uploads/mode1_w200-original.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      const img = getCapturedBody()?.querySelector('img');
      expect(img?.getAttribute('data-mid')).toBe('42');
      expect(img?.getAttribute('src')).toBe('/uploads/original.jpg');
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('リサイズ済みsrc（mode2_w200_h100-original.jpg）+ data-mid付き → オリジナルと一致 → data-mid保持・srcがmedia_root_pathに書き換わる', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/uploads/original.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="/uploads/mode2_w200_h100-original.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      const img = getCapturedBody()?.querySelector('img');
      expect(img?.getAttribute('data-mid')).toBe('42');
      expect(img?.getAttribute('src')).toBe('/uploads/original.jpg');
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('オリジナルsrc + data-mid付き（リグレッション） → srcはmedia_root_pathに書き換わる', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/uploads/original.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="/uploads/original.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      const img = getCapturedBody()?.querySelector('img');
      expect(img?.getAttribute('data-mid')).toBe('42');
      expect(img?.getAttribute('src')).toBe('/uploads/original.jpg');
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('相対パスsrc + data-mid付き → APIの media_root_path と不一致 → data-mid除去', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_id: 42, media_root_path: '/other/img.jpg' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent('<img src="/relative/img.jpg" data-mid="42">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('img')?.getAttribute('data-mid')).toBeNull();
      expect(fetchMock).not.toHaveBeenCalled();
    });
  });

  // ─────────────────────────────────────────────
  // グループ4: fileBlock data-mid の検証
  // ─────────────────────────────────────────────
  describe('fileBlock data-mid の検証', () => {
    it('media_permalink と data-href が一致 → data-mid保持', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/file.pdf', media_root_path: '/file.pdf' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '42', href: '/file.pdf' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBe('42');
    });

    it('media_permalink と data-href が不一致 → data-mid除去', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({
          status: 'success',
          item: { media_permalink: '/other-file.pdf', media_root_path: '/other-file.pdf' },
        })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '42', href: '/file.pdf' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBeNull();
    });

    it('APIが status:failure を返す → data-mid除去', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'failure', message: 'Media not found.' })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '42', href: '/file.pdf' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBeNull();
    });

    it('APIが例外をスロー → data-mid除去（安全のため）', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockRejectedValueOnce(new Error('network error'));
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '42', href: '/file.pdf' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBeNull();
    });

    it('data-midなし → media APIは呼ばれない', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      const { view } = makeEditorView();
      const event = makeClipboardEvent(makeFileBlockHtml({ href: '/file.pdf' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(vi.mocked(fetchClient.get)).not.toHaveBeenCalled();
    });

    it('クエリ付き data-href → クエリを除去して media_permalink と比較 → 一致すれば data-mid保持', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/file.pdf', media_root_path: '/file.pdf' } })
      );
      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const event = makeClipboardEvent(makeFileBlockHtml({ mid: '42', href: '/file.pdf?v=123' }));
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());
      expect(getCapturedBody()?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBe('42');
    });
  });

  // ─────────────────────────────────────────────
  // グループ5: 混在ペースト（fileBlock + standalone img）
  // ─────────────────────────────────────────────
  describe('混在ペースト（fileBlock + standalone img）', () => {
    it('fileBlock と standalone img が混在 → それぞれ独立して data-mid を検証', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      // 1回目: fileBlock の checkFileMediaExists
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/file.pdf', media_root_path: '/file.pdf' } })
      );
      // 2回目: standalone img の checkImgMediaExists
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/img.jpg', media_root_path: '/img.jpg' } })
      );

      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const html = `${makeFileBlockHtml({ mid: '42', href: '/file.pdf' })}<img src="http://localhost/img.jpg" data-mid="99">`;
      const event = makeClipboardEvent(html);
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());

      const body = getCapturedBody();
      expect(body?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBe('42');
      const img = body?.querySelector('img:not([data-type="fileBlock"] img)') as HTMLImageElement | null;
      expect(img?.getAttribute('src')).toBe('/img.jpg');
      expect(img?.getAttribute('data-mid')).toBe('99');
    });

    it('fileBlock と standalone img が混在 → 両方 data-mid 不一致 → それぞれ除去', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/other.pdf', media_root_path: '/other.pdf' } })
      );
      vi.mocked(fetchClient.get).mockResolvedValueOnce(
        mockResponse({ status: 'success', item: { media_permalink: '/other.jpg', media_root_path: '/other.jpg' } })
      );

      const { view } = makeEditorView();
      const { getCapturedBody } = spyOnParseSlice();
      const html = `${makeFileBlockHtml({ mid: '42', href: '/file.pdf' })}<img src="http://localhost/img.jpg" data-mid="99">`;
      const event = makeClipboardEvent(html);
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(view.dispatch).toHaveBeenCalled());

      const body = getCapturedBody();
      expect(body?.querySelector('[data-type="fileBlock"]')?.getAttribute('data-mid')).toBeNull();
      const img = body?.querySelector('img:not([data-type="fileBlock"] img)') as HTMLImageElement | null;
      expect(img?.getAttribute('data-mid')).toBeNull();
    });
  });

  // ─────────────────────────────────────────────
  // グループ6: dispatch の呼び出し確認
  // ─────────────────────────────────────────────
  describe('dispatch の呼び出し確認', () => {
    it('HEAD 200で変換成功 → view.dispatch が呼ばれる', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
      const { view, dispatch } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(dispatch).toHaveBeenCalledTimes(1));
    });

    it('複数画像・一部200一部404 → dispatch は1回だけ呼ばれる', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: false }));
      const { view, dispatch } = makeEditorView();
      const event = makeClipboardEvent('<img src="http://localhost/img1.jpg"><img src="http://localhost/img2.jpg">');
      callHandlePaste(view, event);
      await vi.waitFor(() => expect(dispatch).toHaveBeenCalledTimes(1));
    });
  });
});
