import { useRef, useSyncExternalStore } from 'react';

import { createPendingStore } from './store';

export default function usePendingStore() {
  // 初期化 & 永続化
  const { subscribe, getSnapshot, pushPending, removePending } = useRef(createPendingStore()).current;
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return { snapshot, pushPending, removePending };
}
