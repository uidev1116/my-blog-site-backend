import { useRef, useSyncExternalStore } from 'react';

import { createDialogStore } from './store';

export default function useDialogStore() {
  // 初期化 & 永続化
  const { subscribe, getSnapshot, openDialog, closeDialog } = useRef(createDialogStore()).current;
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return { snapshot, openDialog, closeDialog };
}
