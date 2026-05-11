import { useRef, useState, useSyncExternalStore } from 'react';
import DrawerManager, { DrawerManagerOptions } from './drawer-manager';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UseDrawerManagerOptions extends Partial<DrawerManagerOptions> {}

export default function useDrawerManager(options: UseDrawerManagerOptions = {}) {
  const mostRecentOptions = useRef(options);

  mostRecentOptions.current = options;
  const [manager] = useState(() => new DrawerManager(mostRecentOptions));

  const state = useSyncExternalStore(
    manager.subscribe,
    () => manager.getSnapshot(),
    () => manager.getSnapshot()
  );

  ACMS.Library.drawer = manager;

  return {
    state,
    manager,
  };
}
