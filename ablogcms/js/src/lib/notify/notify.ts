import { pushNotification } from './store/store';
import { NotificationContent, NotificationOptions } from './types';

let NOTIFICATION_ID = 1;

function genId() {
  return `${NOTIFICATION_ID++}`;
}

function notify(content: NotificationContent, options: NotificationOptions = {}) {
  pushNotification({ id: genId(), content, options });
}

function info(content: NotificationContent, options: NotificationOptions = {}) {
  notify(content, { type: 'info', ...options });
}

function success(content: NotificationContent, options: NotificationOptions = {}) {
  notify(content, { type: 'success', ...options });
}

function danger(content: NotificationContent, options: NotificationOptions = {}) {
  notify(content, { type: 'danger', ...options });
}

function warning(content: NotificationContent, options: NotificationOptions = {}) {
  notify(content, { type: 'warning', ...options });
}

notify.info = info;
notify.success = success;
notify.danger = danger;
notify.warning = warning;

export default notify;
