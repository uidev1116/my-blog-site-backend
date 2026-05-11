import { describe, it, expect, vi, beforeEach } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { hideOthers } from 'aria-hidden';

import useAriaHidden from './use-aria-hidden';

vi.mock('aria-hidden', () => ({
  hideOthers: vi.fn(),
}));

describe('useAriaHidden', () => {
  let undoFn: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    undoFn = vi.fn();
    vi.mocked(hideOthers).mockReset();
    vi.mocked(hideOthers).mockReturnValue(undoFn);
  });

  describe('hide', () => {
    it('setRef した要素を hideOthers に渡す', () => {
      const { result } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });

      expect(hideOthers).toHaveBeenCalledWith(node, undefined, undefined);
    });

    it('markerName を引数に取り、hideOthers に転送する', () => {
      const { result } = renderHook(() => useAriaHidden('my-marker'));
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });

      expect(hideOthers).toHaveBeenCalledWith(node, undefined, 'my-marker');
    });

    it('setParentRef した要素が hideOthers の第 2 引数に渡る', () => {
      const { result } = renderHook(() => useAriaHidden());
      const target = document.createElement('div');
      const parent = document.createElement('section');

      act(() => {
        result.current.setRef(target);
        result.current.setParentRef(parent);
      });
      act(() => {
        result.current.hide();
      });

      expect(hideOthers).toHaveBeenCalledWith(target, parent, undefined);
    });

    it('setRef していない状態で呼んでも何もしない', () => {
      const { result } = renderHook(() => useAriaHidden());

      act(() => {
        result.current.hide();
      });

      expect(hideOthers).not.toHaveBeenCalled();
    });
  });

  describe('unhide', () => {
    it('hide() の戻り値である undo を呼ぶ', () => {
      const { result } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });
      act(() => {
        result.current.unhide();
      });

      expect(undoFn).toHaveBeenCalledTimes(1);
    });

    it('hide() を呼んでいない状態で呼んでも何もしない', () => {
      const { result } = renderHook(() => useAriaHidden());

      act(() => {
        result.current.unhide();
      });

      expect(undoFn).not.toHaveBeenCalled();
    });

    it('unhide() を多重に呼んでも undo は 1 回しか実行されない', () => {
      const { result } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });
      act(() => {
        result.current.unhide();
        result.current.unhide();
      });

      expect(undoFn).toHaveBeenCalledTimes(1);
    });
  });

  describe('多重 hide', () => {
    it('hide() を 2 回連続で呼ぶと、前回分の undo が実行されてから新しい hide が走る', () => {
      const firstUndo = vi.fn();
      const secondUndo = vi.fn();
      vi.mocked(hideOthers).mockReset();
      vi.mocked(hideOthers).mockReturnValueOnce(firstUndo).mockReturnValueOnce(secondUndo);

      const { result } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });
      act(() => {
        result.current.hide();
      });

      expect(firstUndo).toHaveBeenCalledTimes(1);
      expect(secondUndo).not.toHaveBeenCalled();
    });
  });

  describe('setRef / setParentRef', () => {
    it('setRef に null を渡すと targetRef がクリアされる（以降 hide が no-op）', () => {
      const { result } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.setRef(null);
      });
      act(() => {
        result.current.hide();
      });

      expect(hideOthers).not.toHaveBeenCalled();
    });

    it('setParentRef に null を渡すと parentRef がクリアされる', () => {
      const { result } = renderHook(() => useAriaHidden());
      const target = document.createElement('div');
      const parent = document.createElement('section');

      act(() => {
        result.current.setRef(target);
        result.current.setParentRef(parent);
      });
      act(() => {
        result.current.setParentRef(null);
      });
      act(() => {
        result.current.hide();
      });

      expect(hideOthers).toHaveBeenCalledWith(target, undefined, undefined);
    });
  });

  describe('cleanup', () => {
    it('アンマウント時に unhide が呼ばれる', () => {
      const { result, unmount } = renderHook(() => useAriaHidden());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.hide();
      });

      unmount();

      expect(undoFn).toHaveBeenCalled();
    });
  });
});
