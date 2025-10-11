import { NotificationStore } from '../types';

let notifications: NotificationStore[] = [];
type Notification = () => void;
const listeners = new Set<Notification>();

function emitChange() {
  for (const listener of listeners) {
    listener();
  }
}

export function pushNotification({ id, content, options }: NotificationStore) {
  notifications = [...notifications, { id, content, options }];
  emitChange();
}

export function removeNotification(id: NotificationStore['id']) {
  notifications = notifications.filter((notification) => notification.id !== id);
  emitChange();
}

export function createNotificationStore() {
  return {
    pushNotification,
    removeNotification,
    subscribe(listener: Notification) {
      listeners.add(listener);

      return () => {
        listeners.delete(listener);
      };
    },
    getSnapshot() {
      return notifications;
    },
  };
}
