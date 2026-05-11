import { describe, it, expect, vi, beforeEach } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { disableBodyScroll, enableBodyScroll } from 'body-scroll-lock';

import useBodyScrollLock from './use-body-scroll-lock';

vi.mock('body-scroll-lock', () => ({
  disableBodyScroll: vi.fn(),
  enableBodyScroll: vi.fn(),
}));

describe('useBodyScrollLock', () => {
  beforeEach(() => {
    vi.mocked(disableBodyScroll).mockClear();
    vi.mocked(enableBodyScroll).mockClear();
  });

  describe('disableScroll', () => {
    it('setRef した要素を disableBodyScroll に渡す', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.disableScroll();
      });

      expect(disableBodyScroll).toHaveBeenCalledWith(node, undefined);
    });

    it('オプションを渡すと disableBodyScroll に転送される', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');
      const opts = { reserveScrollBarGap: true };

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.disableScroll(opts);
      });

      expect(disableBodyScroll).toHaveBeenCalledWith(node, opts);
    });

    it('setRef していない状態で呼んでも何もしない', () => {
      const { result } = renderHook(() => useBodyScrollLock());

      act(() => {
        result.current.disableScroll();
      });

      expect(disableBodyScroll).not.toHaveBeenCalled();
    });
  });

  describe('enableScroll', () => {
    it('setRef した要素を enableBodyScroll に渡す', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.enableScroll();
      });

      expect(enableBodyScroll).toHaveBeenCalledWith(node);
    });

    it('setRef していない状態で呼んでも何もしない', () => {
      const { result } = renderHook(() => useBodyScrollLock());

      act(() => {
        result.current.enableScroll();
      });

      expect(enableBodyScroll).not.toHaveBeenCalled();
    });
  });

  describe('setRef', () => {
    it('null を渡すと targetRef がクリアされ、以降 disableScroll は no-op', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.setRef(null);
      });
      act(() => {
        result.current.disableScroll();
      });

      expect(disableBodyScroll).not.toHaveBeenCalled();
    });

    it('別の要素に切り替えると古い要素のロックが解除される', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const oldNode = document.createElement('div');
      const newNode = document.createElement('div');

      act(() => {
        result.current.setRef(oldNode);
      });
      act(() => {
        result.current.disableScroll();
      });

      expect(disableBodyScroll).toHaveBeenCalledWith(oldNode, undefined);
      expect(enableBodyScroll).not.toHaveBeenCalled();

      act(() => {
        result.current.setRef(newNode);
      });

      expect(enableBodyScroll).toHaveBeenCalledWith(oldNode);
    });

    it('null を渡したときも古い要素のロックが解除される', () => {
      const { result } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.disableScroll();
      });
      act(() => {
        result.current.setRef(null);
      });

      expect(enableBodyScroll).toHaveBeenCalledWith(node);
    });
  });

  describe('cleanup', () => {
    it('アンマウント時に enableScroll が呼ばれる', () => {
      const { result, unmount } = renderHook(() => useBodyScrollLock());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });

      unmount();

      expect(enableBodyScroll).toHaveBeenCalledWith(node);
    });
  });
});
