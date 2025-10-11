import { createContext, forwardRef, useContext, useMemo } from 'react';
import classnames from 'classnames';
import BaseModal from '../modal/base-modal';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface DrawerContextProps extends Pick<React.ComponentProps<typeof BaseModal>, 'onClose' | 'aria-labelledby'> {}

const DrawerContext = createContext<DrawerContextProps | undefined>(undefined);

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface DrawerProps extends React.ComponentProps<typeof BaseModal> {
  placement?: 'left' | 'right' | 'top' | 'bottom';
}

const Drawer = forwardRef<HTMLDivElement, DrawerProps>(
  (
    {
      className,
      backdropClassName,
      dialogClassName,
      closeTimeout = 300,
      onClose,
      'aria-labelledby': ariaLabelledBy,
      placement = 'right',
      children,
      ...props
    },
    ref
  ) => {
    const value = useMemo(() => ({ onClose, 'aria-labelledby': ariaLabelledBy }), [onClose, ariaLabelledBy]);
    return (
      <DrawerContext.Provider value={value}>
        <BaseModal
          ref={ref}
          className={classnames('acms-admin-drawer', className, `acms-admin-drawer-${placement}`)}
          backdropClassName={classnames('acms-admin-drawer-backdrop', backdropClassName)}
          dialogClassName={classnames('acms-admin-drawer-dialog', dialogClassName)}
          closeTimeout={closeTimeout}
          onClose={onClose}
          aria-labelledby={ariaLabelledBy}
          {...props}
        >
          {children}
        </BaseModal>
      </DrawerContext.Provider>
    );
  }
);

Drawer.displayName = 'Drawer';

interface DrawerHeaderProps extends React.HTMLAttributes<HTMLElement> {
  children?: React.ReactNode;
}

const Header = forwardRef<HTMLDivElement, DrawerHeaderProps>(({ children, className, ...props }, ref) => {
  const context = useContext(DrawerContext);
  if (!context) {
    throw new Error('Drawer.Header must be used within a Drawer');
  }
  return (
    <header ref={ref} className={classnames('acms-admin-drawer-header', className)} {...props}>
      <h1 id={context['aria-labelledby']} className="acms-admin-drawer-heading">
        {children}
      </h1>
      <button
        type="button"
        className="acms-admin-drawer-hide acms-admin-icon-delete"
        onClick={context.onClose}
        aria-label={ACMS.i18n('drawer.close')}
      />
    </header>
  );
});

Header.displayName = 'Drawer.Header';

interface DrawerBodyProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const Body = forwardRef<HTMLDivElement, DrawerBodyProps>(({ children, className, ...props }, ref) => (
  <div ref={ref} className={classnames('acms-admin-drawer-body', className)} {...props}>
    {children}
  </div>
));

Body.displayName = 'Drawer.Body';

const Footer = forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ children, className, ...props }, ref) => (
    <footer ref={ref} className={classnames('acms-admin-drawer-footer', className)} {...props}>
      {children}
    </footer>
  )
);

Footer.displayName = 'Drawer.Footer';

export default Object.assign(Drawer, { Header, Body, Footer });
