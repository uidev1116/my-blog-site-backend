import { ColumnResizeDirection } from '@tanstack/react-table';
import classnames from 'classnames';

interface ResizerProps extends React.HTMLAttributes<HTMLDivElement> {
  direction?: ColumnResizeDirection;
  isResizing?: boolean;
}

const Resizer = ({ direction, isResizing = false, className, ...props }: ResizerProps) => (
  <div
    className={classnames('acms-admin-table-column-resizer', direction, className, { 'is-resizeing': isResizing })}
    {...props}
  />
);

export default Resizer;
