import { forwardRef, useEffect } from 'react';
import classnames from 'classnames';
import useDisclosure from '../../hooks/use-disclosure';
import { NotificationContent, NotificationProps as NotificationPropsBase } from '../../lib/notify/types';

interface NotificationBarProps extends NotificationPropsBase {
  children?: NotificationContent;
}

const NotificationBar = forwardRef<HTMLDivElement, NotificationBarProps>(
  ({ onHide, children, type, autoHide = 5000, hideTimeout = 300, role = 'alert', className, ...props }, ref) => {
    const {
      isOpen: isShow,
      beforeClose: beforeHide,
      afterOpen: afterShow,
      close: hide,
    } = useDisclosure({
      defaultIsOpen: true,
      closeTimeout: hideTimeout,
      onAfterClose: onHide,
    });

    useEffect(() => {
      let timeoutId: number;
      if (afterShow && isShow) {
        timeoutId = window.setTimeout(() => {
          hide();
        }, autoHide);
      }

      return () => {
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
      };
    }, [afterShow, isShow, hide, autoHide]);

    if (!isShow) {
      return null;
    }

    return (
      <div
        ref={ref}
        className={classnames(
          'acms-admin-notification-bar',
          {
            'acms-admin-notification-bar-info': type === 'info',
            'acms-admin-notification-bar-success': type === 'success',
            'acms-admin-notification-bar-warning': type === 'warning',
            'acms-admin-notification-bar-danger': type === 'danger',
            'is-after-show': afterShow,
            'is-before-hide': beforeHide,
          },
          className
        )}
        role={role}
        {...props}
      >
        {children}
      </div>
    );
  }
);

NotificationBar.displayName = 'NotificationBar';

export default NotificationBar;
