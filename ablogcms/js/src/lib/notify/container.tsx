import NotificationBar from '../../components/notification-bar';
import { useNotificationStore } from './store';
import { NotificationOptions } from './types';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface NotificationContainerProps extends NotificationOptions {}

const NotificationContainer = ({ onHide: onHideProp, ...props }: NotificationContainerProps) => {
  const { snapshot, removeNotification } = useNotificationStore();

  return (
    <div id="acms-notification-container" className="acms-admin-notification-container">
      {snapshot.map(({ id, content, options }) => {
        const { onHide, ...restOptions } = options;
        return (
          <NotificationBar
            key={id}
            onHide={() => {
              if (onHideProp) {
                onHideProp();
              }
              if (onHide) {
                onHide();
              }
              removeNotification(id);
            }}
            {...{ ...props, ...restOptions }}
          >
            {content}
          </NotificationBar>
        );
      })}
    </div>
  );
};

export default NotificationContainer;
