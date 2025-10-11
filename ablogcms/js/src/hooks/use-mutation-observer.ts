import { useEffect, useRef } from 'react';

export default function useMutationObserver<T extends HTMLElement = HTMLElement>(
  callback: MutationCallback,
  options: MutationObserverInit,
  element?: HTMLElement | (() => HTMLElement) | null
) {
  const observer = useRef<MutationObserver>();
  const ref = useRef<T | null>(null);

  useEffect(() => {
    const target = typeof element === 'function' ? element() : element;

    if (target || ref.current) {
      observer.current = new MutationObserver(callback);
      observer.current.observe(target || ref.current!, options);
    }

    return () => {
      observer.current?.disconnect();
    };
  }, [callback, options, element]);

  return ref;
}
