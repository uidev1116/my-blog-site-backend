import classnames from 'classnames';

interface UnitStatusGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
}

const UnitStatusGroup = ({ className, children, ...props }: UnitStatusGroupProps) => {
  return (
    <div className={classnames('acms-admin-unit-toolbar-status-group', className)} {...props}>
      {children}
    </div>
  );
};

export default UnitStatusGroup;
