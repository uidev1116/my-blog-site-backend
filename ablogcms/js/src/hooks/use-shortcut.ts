import { useEffect } from 'react';
import useCallbackRef from './use-callback-ref';

function isMac() {
  return typeof window !== 'undefined' ? navigator.platform.toUpperCase().indexOf('MAC') >= 0 : false;
}

function isModKeyPressed(event: KeyboardEvent) {
  return isMac() ? event.metaKey : event.ctrlKey;
}

interface UseShortcutOptions {
  altKey?: boolean;
  ctrlKey?: boolean;
  metaKey?: boolean;
  shiftKey?: boolean;
  modKey?: boolean;
  key: KeyboardEvent['key'];
  onKeyDown: (event: KeyboardEvent) => void;
  eventTarget: HTMLElement | Window;
  enabled?: boolean;
}

export default function useShortcut({
  altKey,
  ctrlKey,
  metaKey,
  shiftKey,
  modKey,
  key,
  onKeyDown,
  eventTarget = window,
  enabled = true,
}: UseShortcutOptions) {
  const onKeyDownCallback = useCallbackRef(onKeyDown);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (!enabled) {
        return;
      }

      // Check if the specified modifier keys are pressed
      if (altKey && !event.altKey) {
        return;
      }
      if (ctrlKey && !event.ctrlKey) {
        return;
      }
      if (metaKey && !event.metaKey) {
        return;
      }
      if (shiftKey && !event.shiftKey) {
        return;
      }
      if (modKey && !isModKeyPressed(event)) {
        return;
      }

      // Check if any unspecified modifier keys are pressed
      if (!altKey && event.altKey) {
        return;
      }
      if (!ctrlKey && !modKey && event.ctrlKey) {
        return;
      }
      if (!metaKey && !modKey && event.metaKey) {
        return;
      }
      if (!shiftKey && event.shiftKey) {
        return;
      }

      if (!modKey && isModKeyPressed(event)) {
        return;
      }

      if (event.key !== key) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      onKeyDownCallback?.(event);
    };

    eventTarget.addEventListener('keydown', handleKeyDown as EventListener);
    return () => eventTarget.removeEventListener('keydown', handleKeyDown as EventListener);
  }, [altKey, ctrlKey, key, metaKey, onKeyDownCallback, shiftKey, modKey, eventTarget, enabled]);
}
