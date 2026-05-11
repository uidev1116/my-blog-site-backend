import { Extension, Editor } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import { EditorView } from '@tiptap/pm/view';
import { DOMParser as PMDOMParser } from '@tiptap/pm/model';
import type { MediaItem } from '@features/media/types';
import { fetchClient } from '../../../../lib/fetch-client';

// 絶対URL（http/https）の場合のみ、同一サーバー上の画像かHEADで確認して相対パスに変換する
async function convertImgSrcToRelative(img: HTMLImageElement): Promise<void> {
  const src = img.getAttribute('src');
  if (!src || !/^https?:\/\//i.test(src)) {
    return;
  }
  try {
    const root = ACMS.Config.root.replace(/\/$/, '');
    const url = new URL(src);
    const relativePath = url.pathname;
    const checkUrl = `${location.origin}${root}${relativePath}`;
    const res = await fetch(checkUrl, { method: 'HEAD' });
    if (res.ok) {
      img.setAttribute('src', `${root}${relativePath}`);
    }
  } catch (e) {
    console.warn('ペースト画像のURL変換処理に失敗しました:', e); // eslint-disable-line no-console
  }
}

// media-edit.json API を呼び出してメディアアイテムを取得する
async function fetchMediaItem(mediaId: number): Promise<MediaItem | null> {
  const endpoint = ACMS.Library.acmsLink({
    tpl: 'ajax/edit/media-edit.json',
    bid: ACMS.Config.bid,
    searchParams: { _mid: mediaId },
  });
  try {
    const res = await fetchClient.get<{ status: 'failure'; message: string } | { status: 'success'; item: MediaItem }>(
      endpoint
    );
    return res.data.status === 'success' ? res.data.item : null;
  } catch {
    return null;
  }
}

// リサイズプレフィックス（mode{N}_[w{W}][_h{H}][_{color}]-）を除去してオリジナルパスを返す
function stripResizePrefix(imgPath: string): string {
  const lastSlash = imgPath.lastIndexOf('/');
  const dir = lastSlash >= 0 ? imgPath.slice(0, lastSlash + 1) : '';
  const basename = lastSlash >= 0 ? imgPath.slice(lastSlash + 1) : imgPath;
  const match = basename.match(/^mode\d+_[^-]*-(.+)$/);
  if (!match) return imgPath;
  return dir + match[1];
}

// img の data-mid がこのCMSのメディアか確認する。
// media_root_path と img src を比較することで、IDが偶然一致するだけのケースを除外する。
// リサイズ済み画像（mode{N}_...-{original}）も同一メディアとして判定する。
// 一致した場合は MediaItem を返し、不一致の場合は null を返す。
async function checkImgMediaExists(img: HTMLImageElement): Promise<MediaItem | null> {
  const mediaId = img.getAttribute('data-mid');
  if (!mediaId) {
    return null;
  }
  const item = await fetchMediaItem(parseInt(mediaId, 10));
  if (!item) {
    return null;
  }
  const mediaPath = item.media_root_path.split('?')[0];
  const imgPath = (img.getAttribute('src') ?? '').split('?')[0];
  if (mediaPath === imgPath || mediaPath === stripResizePrefix(imgPath)) {
    return item;
  }
  return null;
}

// fileBlock の data-mid がこのCMSのメディアか確認する。
// media_permalink と data-href を比較することで、IDが偶然一致するだけのケースを除外する。
async function checkFileMediaExists(el: HTMLElement): Promise<boolean> {
  const mediaId = el.getAttribute('data-mid');
  if (!mediaId) {
    return false;
  }
  const item = await fetchMediaItem(parseInt(mediaId, 10));
  if (!item) {
    return false;
  }
  const permalink = item.media_permalink.split('?')[0]; // クエリを除去して比較する
  const link = el.querySelector('a');
  if (link === null) {
    return false;
  }
  const href = (link.getAttribute('href') ?? '').split('?')[0]; // クエリを除去して比較する

  if (permalink !== href) {
    return false;
  }
  return true;
}

export const pastePlugin = (editor: Editor) =>
  new Plugin({
    props: {
      handlePaste(view: EditorView, event: ClipboardEvent): boolean {
        const html = event.clipboardData?.getData('text/html');
        if (!html) {
          return false;
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const fileBlocks = Array.from(doc.querySelectorAll<HTMLElement>('div[data-type="fileBlock"]'));
        const imgs = Array.from(doc.querySelectorAll('img'));

        if (fileBlocks.length === 0 && imgs.length === 0) {
          return false;
        }

        const fileBlockChecks = fileBlocks.map(async (el) => {
          if (el.hasAttribute('data-mid')) {
            const mediaExists = await checkFileMediaExists(el);
            if (!mediaExists) {
              el.removeAttribute('data-mid');
            }
          }
        });

        // fileBlock 外の standalone img を処理する
        const standaloneImgs = imgs.filter((img) => !img.closest('div[data-type="fileBlock"]'));
        const imgChecks = standaloneImgs.map(async (img) => {
          await convertImgSrcToRelative(img);
          if (img.hasAttribute('data-mid')) {
            const mediaItem = await checkImgMediaExists(img);
            if (mediaItem !== null) {
              img.setAttribute('src', mediaItem.media_root_path);
            } else {
              img.removeAttribute('data-mid');
            }
          }
        });

        Promise.all([...fileBlockChecks, ...imgChecks]).then(() => {
          const slice = PMDOMParser.fromSchema(editor.schema).parseSlice(doc.body);
          const transaction = view.state.tr.replaceSelection(slice);
          view.dispatch(transaction);
        });

        return true;
      },
    },
  });

export const PasteHandler = Extension.create({
  name: 'pasteHandler',

  addProseMirrorPlugins() {
    return [pastePlugin(this.editor)];
  },
});
