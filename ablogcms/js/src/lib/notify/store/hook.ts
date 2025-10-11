import { useRef, useSyncExternalStore } from 'react';

import { createNotificationStore } from './store';

export default function useNotificationStore() {
  // 初期化 & 永続化
  const { subscribe, getSnapshot, pushNotification, removeNotification } = useRef(createNotificationStore()).current;
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return { snapshot, pushNotification, removeNotification };
}
