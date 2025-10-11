import { forwardRef } from 'react';
import classNames from 'classnames';
import { Icon } from '@components/icon';

interface DraggableButtonProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'type' | 'children'> {
  streched?: boolean;
  size?: 'small' | 'default';
}

const DraggableButton = forwardRef<HTMLButtonElement, DraggableButtonProps>(
  ({ className, streched = false, size = 'default', ...props }, ref) => (
    <button
      ref={ref}
      type="button"
      className={classNames('acms-admin-btn-draggable', className, {
        'acms-admin-stretch': streched,
        [`acms-admin-btn-draggable-${size}`]: size !== 'default',
      })}
      aria-label={ACMS.i18n('draggable_button.label')}
      {...props}
    >
      <Icon name="drag_indicator" />
    </button>
  )
);

DraggableButton.displayName = 'DraggableButton';

export default DraggableButton;
