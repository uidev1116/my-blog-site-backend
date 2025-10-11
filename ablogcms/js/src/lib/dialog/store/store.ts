import { DialogStore } from '../types';

let store: DialogStore | { isOpen: false; onClose: () => void } = { isOpen: false, onClose: () => {} };
type DialogListener = () => void;
const listeners = new Set<DialogListener>();

function emitChange() {
  for (const listener of listeners) {
    listener();
  }
}

export function openDialog(dialog: DialogStore) {
  store = dialog;
  emitChange();
}

export function closeDialog() {
  if (store.isOpen) {
    store = { isOpen: false, onClose: () => {} };
    emitChange();
  }
}

export function createDialogStore() {
  return {
    openDialog,
    closeDialog,
    subscribe(listener: DialogListener) {
      listeners.add(listener);
      return () => {
        listeners.delete(listener);
      };
    },
    getSnapshot() {
      return store;
    },
  };
}
