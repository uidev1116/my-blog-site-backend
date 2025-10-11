import { useCallback, useRef } from 'react';
import { tabbable, type FocusableElement } from 'tabbable';
import { handleRef } from '../utils/react';

type Orientation = 'horizontal' | 'vertical';
type Direction = 'ltr' | 'rtl';

export interface UseToolbarOptions {
  orientation?: Orientation;
  direction?: Direction;
}

export default function useToolbar(options: UseToolbarOptions = {}) {
  const { orientation = 'horizontal', direction = 'ltr' } = options;

  const toolbarRef = useRef<HTMLElement | null>(null);
  const lastFocused = useRef<FocusableElement | null>(null);

  // キーボードイベントハンドラ
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLElement>) => {
      if (!event.currentTarget.contains(event.target as HTMLElement)) {
        return;
      }

      if (!toolbarRef.current) {
        return;
      }

      const isHorizontal = orientation === 'horizontal';
      const isRTL = direction === 'rtl';

      // ツールバー内の全てのフォーカス可能な要素を取得
      const focasableElements = tabbable(toolbarRef.current);

      if (!focasableElements.length) {
        return;
      }

      const currentElement = document.activeElement;
      const currentIndex =
        currentElement !== null ? Array.from(focasableElements).indexOf(currentElement as HTMLElement) : 0;
      let nextIndex = currentIndex;

      switch (event.key) {
        case 'ArrowRight': {
          if (isHorizontal) {
            // Prevent arrow keys from being handled by nested action groups.
            event.stopPropagation();
            event.preventDefault();
            nextIndex = isRTL ? currentIndex - 1 : currentIndex + 1;
          }
          break;
        }
        case 'ArrowLeft': {
          if (isHorizontal) {
            // Prevent arrow keys from being handled by nested action groups.
            event.stopPropagation();
            event.preventDefault();
            nextIndex = isRTL ? currentIndex + 1 : currentIndex - 1;
          }
          break;
        }
        case 'ArrowDown': {
          if (!isHorizontal) {
            // Prevent arrow keys from being handled by nested action groups.
            event.stopPropagation();
            event.preventDefault();
            nextIndex = currentIndex + 1;
          }
          break;
        }
        case 'ArrowUp': {
          if (!isHorizontal) {
            // Prevent arrow keys from being handled by nested action groups.
            event.stopPropagation();
            event.preventDefault();
            nextIndex = currentIndex - 1;
          }
          break;
        }
        case 'Home': {
          // Prevent arrow keys from being handled by nested action groups.
          event.stopPropagation();
          event.preventDefault();
          nextIndex = 0;
          break;
        }
        case 'End': {
          // Prevent arrow keys from being handled by nested action groups.
          event.stopPropagation();
          event.preventDefault();
          nextIndex = focasableElements.length - 1;
          break;
        }
        case 'Tab': {
          // When the tab key is pressed, we want to move focus
          // out of the entire toolbar. To do this, move focus
          // to the first or last focusable child, and let the
          // browser handle the Tab key as usual from there.
          event.stopPropagation();
          lastFocused.current = document.activeElement as HTMLElement;
          if (event.shiftKey) {
            focasableElements[0]?.focus();
          } else {
            focasableElements[focasableElements.length - 1]?.focus();
          }
          return;
        }
        default:
          return;
      }

      // インデックスの正規化
      if (nextIndex < 0) {
        nextIndex = focasableElements.length - 1;
      } else if (nextIndex >= focasableElements.length) {
        nextIndex = 0;
      }

      // 新しい要素にフォーカスを移動
      const nextElement = focasableElements[nextIndex];
      if (nextElement) {
        nextElement.focus();
        lastFocused.current = nextElement;
      }
    },
    [orientation, direction]
  );

  // Record the last focused child when focus moves out of the toolbar.
  const handleBlur = useCallback((event: React.FocusEvent<HTMLElement>) => {
    if (!event.currentTarget.contains(event.relatedTarget) && !lastFocused.current) {
      lastFocused.current = event.target as HTMLElement;
    }
  }, []);

  // Restore focus to the last focused child when focus returns into the toolbar.
  // If the element was removed, do nothing, either the first item in the first group,
  // or the last item in the last group will be focused, depending on direction.
  const handleFocus = useCallback((event: React.FocusEvent<HTMLElement>) => {
    if (
      lastFocused.current &&
      !event.currentTarget.contains(event.relatedTarget) &&
      toolbarRef.current?.contains(event.target)
    ) {
      lastFocused.current?.focus();
      lastFocused.current = null;
    }
  }, []);

  // ツールバープロパティの取得
  const toolbarProps = useCallback(
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (userProps: React.ComponentPropsWithoutRef<any> = {}) => {
      const { ref, onKeyDownCapture, onFocusCapture, onBlurCapture, ...rest } = userProps;
      return {
        ...rest,
        ref: handleRef(ref, toolbarRef),
        role: 'toolbar',
        'aria-orientation': orientation,
        onKeyDownCapture: (event: React.KeyboardEvent<HTMLElement>) => {
          if (onKeyDownCapture) {
            onKeyDownCapture(event);
          }
          handleKeyDown(event);
        },
        onFocusCapture: (event: React.FocusEvent<HTMLElement>) => {
          if (onFocusCapture) {
            onFocusCapture(event);
          }
          handleFocus(event);
        },
        onBlurCapture: (event: React.FocusEvent<HTMLElement>) => {
          if (onBlurCapture) {
            onBlurCapture(event);
          }
          handleBlur(event);
        },
      };
    },
    [orientation, handleKeyDown, handleFocus, handleBlur]
  );

  return { toolbarProps };
}
