import { useRef, useState, useSyncExternalStore } from 'react';
import ModalManager, { ModalManagerOptions } from './modal-manager';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UseModalManagerOptions extends Partial<ModalManagerOptions> {}

export default function useModalManager(options: UseModalManagerOptions = {}) {
  const mostRecentOptions = useRef(options);

  mostRecentOptions.current = options;
  const [manager] = useState(() => new ModalManager(mostRecentOptions));

  const state = useSyncExternalStore(
    manager.subscribe,
    () => manager.getSnapshot(),
    () => manager.getSnapshot()
  );

  ACMS.Library.modal = manager;

  return {
    state,
    manager,
  };
}
