import { PendingStore } from '../types';

let pending: PendingStore | null = null;
type Pending = () => void;
const listeners = new Set<Pending>();

function emitChange() {
  for (const listener of listeners) {
    listener();
  }
}

export function pushPending({ type, message }: PendingStore) {
  pending = { type, message };
  emitChange();
}

export function removePending() {
  pending = null;
  emitChange();
}

export function createPendingStore() {
  return {
    pushPending,
    removePending,
    subscribe(listener: Pending) {
      listeners.add(listener);

      return () => {
        listeners.delete(listener);
      };
    },
    getSnapshot() {
      return pending;
    },
  };
}
