import classnames from 'classnames';
import { useCallback, forwardRef, cloneElement, isValidElement } from 'react';

export interface AlertProps extends React.HTMLAttributes<HTMLDivElement> {
  onClose?: () => void;
  type?: 'info' | 'success' | 'warning' | 'danger';
  icon?: React.ReactNode;
}

const Alert = forwardRef<HTMLDivElement, AlertProps>(
  ({ className, onClose, children, type = '', icon, role = 'alert', ...props }, ref) => {
    const handleClick = useCallback(() => {
      if (onClose) {
        onClose();
      }
    }, [onClose]);

    return (
      <div
        ref={ref}
        className={classnames(
          'acms-admin-alert',
          {
            'acms-admin-alert-info': type === 'info',
            'acms-admin-alert-success': type === 'success',
            'acms-admin-alert-warning': type === 'warning',
            'acms-admin-alert-danger': type === 'danger',
          },
          className,
          { 'acms-admin-alert-icon': icon }
        )}
        role={role}
        {...props}
      >
        {isValidElement(icon) &&
          cloneElement(icon, {
            ...icon.props,
            className: classnames(icon.props.className, 'acms-admin-alert-icon-before'),
            'aria-hidden': 'true',
          })}
        {children}
        {onClose && (
          <button
            type="button"
            aria-label={ACMS.i18n('alert.close')}
            onClick={handleClick}
            className="acms-admin-alert-close acms-admin-alert-icon-after"
          >
            ×
          </button>
        )}
      </div>
    );
  }
);

Alert.displayName = 'Alert';

export default Alert;
