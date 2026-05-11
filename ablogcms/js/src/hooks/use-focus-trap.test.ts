import { describe, it, expect, vi, beforeEach } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import type { FocusTrap, Options } from 'focus-trap';
import { createFocusTrap } from 'focus-trap';

import useFocusTrap from './use-focus-trap';

vi.mock('focus-trap', () => ({
  createFocusTrap: vi.fn(),
}));

type MockTrap = FocusTrap & {
  activate: ReturnType<typeof vi.fn>;
  deactivate: ReturnType<typeof vi.fn>;
  pause: ReturnType<typeof vi.fn>;
  unpause: ReturnType<typeof vi.fn>;
  updateContainerElements: ReturnType<typeof vi.fn>;
};

const createMockTrap = (): MockTrap =>
  ({
    active: false,
    paused: false,
    activate: vi.fn(),
    deactivate: vi.fn(),
    pause: vi.fn(),
    unpause: vi.fn(),
    updateContainerElements: vi.fn(),
  }) as unknown as MockTrap;

describe('useFocusTrap', () => {
  let mockTrap: MockTrap;
  let internalOptions: Options;

  beforeEach(() => {
    mockTrap = createMockTrap();
    vi.mocked(createFocusTrap).mockReset();
    vi.mocked(createFocusTrap).mockImplementation((_node, options) => {
      internalOptions = options as Options;
      return mockTrap;
    });
  });

  describe('setRef', () => {
    it('node を渡すと createFocusTrap が呼ばれる', () => {
      const { result } = renderHook(() => useFocusTrap());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });

      expect(createFocusTrap).toHaveBeenCalledTimes(1);
      expect(createFocusTrap).toHaveBeenCalledWith(node, expect.any(Object));
    });

    it('node のみのときは何も呼び出さない', () => {
      const { result } = renderHook(() => useFocusTrap());

      act(() => {
        result.current.setRef(null);
      });

      expect(createFocusTrap).not.toHaveBeenCalled();
    });

    it('既にトラップが作られている場合は updateContainerElements を呼ぶ', () => {
      const { result } = renderHook(() => useFocusTrap());
      const node1 = document.createElement('div');
      const node2 = document.createElement('div');

      act(() => {
        result.current.setRef(node1);
      });
      act(() => {
        result.current.setRef(node2);
      });

      expect(createFocusTrap).toHaveBeenCalledTimes(1);
      expect(mockTrap.updateContainerElements).toHaveBeenCalledWith(node2);
    });

    it('callback ref として戻り値は void である（React 19 互換）', () => {
      const { result } = renderHook(() => useFocusTrap());
      const node1 = document.createElement('div');
      const node2 = document.createElement('div');

      act(() => {
        result.current.setRef(node1);
      });

      // 既存トラップを更新するパスでも戻り値は undefined
      let returnValue: unknown = 'initial';
      act(() => {
        returnValue = result.current.setRef(node2);
      });
      expect(returnValue).toBeUndefined();
    });

    it('null を渡すと既存トラップが deactivate される', () => {
      const { result } = renderHook(() => useFocusTrap());
      const node = document.createElement('div');

      act(() => {
        result.current.setRef(node);
      });
      act(() => {
        result.current.setRef(null);
      });

      expect(mockTrap.deactivate).toHaveBeenCalled();
    });
  });

  describe('activate / deactivate / pause / unpause', () => {
    it('それぞれが trap の対応メソッドを呼ぶ', () => {
      const { result } = renderHook(() => useFocusTrap());

      act(() => {
        result.current.setRef(document.createElement('div'));
      });

      const activateOpts = { onActivate: vi.fn() };
      const deactivateOpts = { onDeactivate: vi.fn() };

      act(() => result.current.activate(activateOpts));
      act(() => result.current.deactivate(deactivateOpts));
      act(() => result.current.pause());
      act(() => result.current.unpause());

      expect(mockTrap.activate).toHaveBeenCalledWith(activateOpts);
      expect(mockTrap.deactivate).toHaveBeenCalledWith(deactivateOpts);
      expect(mockTrap.pause).toHaveBeenCalledTimes(1);
      expect(mockTrap.unpause).toHaveBeenCalledTimes(1);
    });

    it('trap が作られていない状態で activate しても落ちない', () => {
      const { result } = renderHook(() => useFocusTrap());

      expect(() => {
        act(() => result.current.activate());
        act(() => result.current.deactivate());
        act(() => result.current.pause());
        act(() => result.current.unpause());
      }).not.toThrow();
    });
  });

  describe('isActive / isPaused の状態反映', () => {
    it('onActivate ハンドラで isActive=true になり、外部 options.onActivate も呼ばれる', () => {
      const onActivate = vi.fn();
      const { result } = renderHook(() => useFocusTrap({ onActivate }));

      act(() => {
        result.current.setRef(document.createElement('div'));
      });
      expect(result.current.isActive).toBe(false);

      act(() => {
        internalOptions.onActivate?.();
      });

      expect(result.current.isActive).toBe(true);
      expect(onActivate).toHaveBeenCalledTimes(1);
    });

    it('onDeactivate ハンドラで isActive=false に戻り、外部 options.onDeactivate も呼ばれる', () => {
      const onDeactivate = vi.fn();
      const { result } = renderHook(() => useFocusTrap({ onDeactivate }));

      act(() => {
        result.current.setRef(document.createElement('div'));
      });
      act(() => {
        internalOptions.onActivate?.();
      });

      act(() => {
        internalOptions.onDeactivate?.();
      });

      expect(result.current.isActive).toBe(false);
      expect(onDeactivate).toHaveBeenCalledTimes(1);
    });

    it('pause / unpause で isPaused が切り替わる', () => {
      const { result } = renderHook(() => useFocusTrap());

      act(() => {
        result.current.setRef(document.createElement('div'));
      });

      act(() => result.current.pause());
      expect(result.current.isPaused).toBe(true);

      act(() => result.current.unpause());
      expect(result.current.isPaused).toBe(false);
    });
  });

  describe('cleanup', () => {
    it('アンマウント時に deactivate が呼ばれる', () => {
      const { result, unmount } = renderHook(() => useFocusTrap());

      act(() => {
        result.current.setRef(document.createElement('div'));
      });

      unmount();

      expect(mockTrap.deactivate).toHaveBeenCalled();
    });
  });

  describe('options の最新参照', () => {
    it('onActivate を再レンダで差し替えても、最新のものが trap から呼ばれる', () => {
      const firstCallback = vi.fn();
      const secondCallback = vi.fn();

      const { result, rerender } = renderHook(({ onActivate }) => useFocusTrap({ onActivate }), {
        initialProps: { onActivate: firstCallback },
      });

      act(() => {
        result.current.setRef(document.createElement('div'));
      });

      rerender({ onActivate: secondCallback });

      act(() => {
        internalOptions.onActivate?.();
      });

      expect(firstCallback).not.toHaveBeenCalled();
      expect(secondCallback).toHaveBeenCalledTimes(1);
    });

    it('options が毎レンダで新しいオブジェクトでも setRef は安定する', () => {
      const { result, rerender } = renderHook(() => useFocusTrap({}));
      const firstSetRef = result.current.setRef;

      rerender();

      expect(result.current.setRef).toBe(firstSetRef);
    });
  });
});
