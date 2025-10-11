export type NotificationType = 'success' | 'danger' | 'warning' | 'info';

export type NotificationContent = React.ReactNode;

export interface NotificationProps extends React.HTMLAttributes<HTMLDivElement> {
  type?: NotificationType;
  onHide?: () => void;
  autoHide?: number;
  hideTimeout?: number;
}

export type NotificationOptions = NotificationProps;

export interface NotificationStore {
  id: string;
  content: NotificationContent;
  options: NotificationOptions;
}
