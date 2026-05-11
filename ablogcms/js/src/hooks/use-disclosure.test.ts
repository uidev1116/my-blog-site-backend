import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { act, renderHook } from '@testing-library/react';

import useDisclosure from './use-disclosure';

describe('useDisclosure', () => {
  describe('初期状態', () => {
    it('引数なしのときは閉じている', () => {
      const { result } = renderHook(() => useDisclosure());

      expect(result.current.isOpen).toBe(false);
      expect(result.current.beforeClose).toBe(false);
      expect(result.current.afterOpen).toBe(false);
    });

    it('defaultIsOpen=true のときは開いている', () => {
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true }));

      expect(result.current.isOpen).toBe(true);
      expect(result.current.afterOpen).toBe(false);
    });

    it('controlled の isOpen=true で開いた状態になる', () => {
      const { result } = renderHook(({ isOpen }) => useDisclosure({ isOpen }), {
        initialProps: { isOpen: true },
      });

      expect(result.current.isOpen).toBe(true);
    });

    it('controlled の isOpen=false で閉じた状態になり、beforeClose は trigger されない', () => {
      const { result } = renderHook(({ isOpen }) => useDisclosure({ isOpen }), {
        initialProps: { isOpen: false },
      });

      expect(result.current.isOpen).toBe(false);
      expect(result.current.beforeClose).toBe(false);
    });
  });

  describe('uncontrolled の操作', () => {
    it('open() で開く', () => {
      const { result } = renderHook(() => useDisclosure());

      act(() => {
        result.current.open();
      });

      expect(result.current.isOpen).toBe(true);
    });

    it('toggle() で開閉が切り替わる', () => {
      const { result } = renderHook(() => useDisclosure());

      act(() => {
        result.current.toggle();
      });
      expect(result.current.isOpen).toBe(true);

      act(() => {
        result.current.toggle();
      });
      // beforeClose アニメーション中は isOpen=true のまま
      expect(result.current.beforeClose).toBe(true);
    });

    it('すでに閉じている状態で close() しても何も起きない', () => {
      const { result } = renderHook(() => useDisclosure());

      act(() => {
        result.current.close();
      });

      expect(result.current.beforeClose).toBe(false);
      expect(result.current.isOpen).toBe(false);
    });
  });

  describe('閉じるアニメーション（beforeClose と closeTimeout）', () => {
    beforeEach(() => {
      vi.useFakeTimers();
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('close() で beforeClose=true になり、isOpen は true のまま保持される', () => {
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, closeTimeout: 100 }));

      act(() => {
        result.current.close();
      });

      expect(result.current.beforeClose).toBe(true);
      expect(result.current.isOpen).toBe(true);
    });

    it('closeTimeout 経過後に完全に閉じる', () => {
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, closeTimeout: 100 }));

      act(() => {
        result.current.close();
      });

      act(() => {
        vi.advanceTimersByTime(100);
      });

      expect(result.current.isOpen).toBe(false);
      expect(result.current.beforeClose).toBe(false);
      expect(result.current.afterOpen).toBe(false);
    });

    it('controlled で isOpen を true→false に変えると beforeClose 経由で閉じる', () => {
      const { result, rerender } = renderHook(({ isOpen }) => useDisclosure({ isOpen, closeTimeout: 100 }), {
        initialProps: { isOpen: true },
      });

      rerender({ isOpen: false });

      expect(result.current.beforeClose).toBe(true);
      expect(result.current.isOpen).toBe(true);

      act(() => {
        vi.advanceTimersByTime(100);
      });

      expect(result.current.isOpen).toBe(false);
      expect(result.current.beforeClose).toBe(false);
    });

    it('閉じアニメ中に open() すると beforeClose を解除して再開する', () => {
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, closeTimeout: 100 }));

      act(() => {
        result.current.close();
      });
      expect(result.current.beforeClose).toBe(true);

      act(() => {
        result.current.open();
      });

      expect(result.current.beforeClose).toBe(false);
      expect(result.current.isOpen).toBe(true);
      expect(result.current.afterOpen).toBe(false);
    });

    it('閉じアニメ中に toggle() しても再開する', () => {
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, closeTimeout: 100 }));

      act(() => {
        result.current.close();
      });
      expect(result.current.beforeClose).toBe(true);

      act(() => {
        result.current.toggle();
      });

      expect(result.current.beforeClose).toBe(false);
      expect(result.current.isOpen).toBe(true);
    });
  });

  describe('ライフサイクルコールバック', () => {
    beforeEach(() => {
      vi.useFakeTimers();
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('開いた後 rAF で afterOpen=true になり、onAfterOpen が呼ばれる', async () => {
      const onAfterOpen = vi.fn();
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, onAfterOpen }));

      expect(result.current.afterOpen).toBe(false);
      expect(onAfterOpen).not.toHaveBeenCalled();

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      expect(result.current.afterOpen).toBe(true);
      expect(onAfterOpen).toHaveBeenCalledTimes(1);
    });

    it('閉じた後 closeTimeout 経過で onAfterClose が呼ばれる', async () => {
      const onAfterClose = vi.fn();
      const { result } = renderHook(() => useDisclosure({ defaultIsOpen: true, closeTimeout: 100, onAfterClose }));

      // afterOpen を確定させる
      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      act(() => {
        result.current.close();
      });
      expect(onAfterClose).not.toHaveBeenCalled();

      act(() => {
        vi.advanceTimersByTime(100);
      });

      expect(onAfterClose).toHaveBeenCalledTimes(1);
    });

    it('rAF が走る前にアンマウントされた場合は onAfterOpen が呼ばれない', async () => {
      const onAfterOpen = vi.fn();
      const { unmount } = renderHook(() => useDisclosure({ defaultIsOpen: true, onAfterOpen }));

      unmount();

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      expect(onAfterOpen).not.toHaveBeenCalled();
    });

    it('onAfterOpen を再レンダで差し替えると最新のものが呼ばれる（stale closure 回避）', async () => {
      const firstCallback = vi.fn();
      const secondCallback = vi.fn();

      const { rerender } = renderHook(({ onAfterOpen }) => useDisclosure({ defaultIsOpen: true, onAfterOpen }), {
        initialProps: { onAfterOpen: firstCallback },
      });

      rerender({ onAfterOpen: secondCallback });

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      expect(firstCallback).not.toHaveBeenCalled();
      expect(secondCallback).toHaveBeenCalledTimes(1);
    });

    it('onAfterClose を再レンダで差し替えると最新のものが呼ばれる', async () => {
      const firstCallback = vi.fn();
      const secondCallback = vi.fn();

      const { result, rerender } = renderHook(
        ({ onAfterClose }) => useDisclosure({ defaultIsOpen: true, closeTimeout: 100, onAfterClose }),
        { initialProps: { onAfterClose: firstCallback } }
      );

      await act(async () => {
        await vi.runOnlyPendingTimersAsync();
      });

      rerender({ onAfterClose: secondCallback });

      act(() => {
        result.current.close();
      });
      act(() => {
        vi.advanceTimersByTime(100);
      });

      expect(firstCallback).not.toHaveBeenCalled();
      expect(secondCallback).toHaveBeenCalledTimes(1);
    });
  });
});
