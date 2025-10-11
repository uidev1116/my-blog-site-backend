import { createContext, forwardRef, useContext, useMemo } from 'react';
import classnames from 'classnames';
import BaseModal from './base-modal';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface ModalContextProps extends Pick<React.ComponentProps<typeof BaseModal>, 'onClose' | 'aria-labelledby'> {}

const ModalContext = createContext<ModalContextProps | undefined>(undefined);

interface ModalProps extends React.ComponentProps<typeof BaseModal> {
  size?: 'small' | 'medium' | 'large';
  isCentered?: boolean;
  isScrollable?: boolean;
}

const Modal = forwardRef<HTMLDivElement, ModalProps>(
  (
    {
      className,
      backdropClassName,
      dialogClassName,
      closeTimeout = 300,
      onClose,
      'aria-labelledby': ariaLabelledBy,
      size,
      isCentered = false,
      isScrollable = false,
      children,
      ...props
    },
    ref
  ) => {
    const value = useMemo(
      () => ({ onClose, 'aria-labelledby': ariaLabelledBy, isCentered }),
      [onClose, ariaLabelledBy, isCentered]
    );
    return (
      <ModalContext.Provider value={value}>
        <BaseModal
          ref={ref}
          className={classnames('acms-admin-modal', className)}
          backdropClassName={classnames('acms-admin-modal-backdrop', backdropClassName)}
          dialogClassName={classnames('acms-admin-modal-dialog', dialogClassName, size, {
            'acms-admin-modal-dialog-centered': isCentered,
            'acms-admin-modal-dialog-scrollable': isScrollable,
          })}
          closeTimeout={closeTimeout}
          afterOpenClassName="in"
          beforeCloseClassName="out"
          onClose={onClose}
          aria-labelledby={ariaLabelledBy}
          {...props}
        >
          <div className="acms-admin-modal-content">{children}</div>
        </BaseModal>
      </ModalContext.Provider>
    );
  }
);

Modal.displayName = 'Modal';

interface ModalHeaderProps extends React.HTMLAttributes<HTMLElement> {
  children?: React.ReactNode;
}

const Header = forwardRef<HTMLDivElement, ModalHeaderProps>(({ children, className, ...props }, ref) => {
  const context = useContext(ModalContext);
  if (!context) {
    throw new Error('Modal.Header must be used within a Modal');
  }
  return (
    <header ref={ref} className={classnames('acms-admin-modal-header', className)} {...props}>
      <h1 id={context['aria-labelledby']} className="acms-admin-modal-heading">
        {children}
      </h1>
      <button
        type="button"
        className="acms-admin-modal-hide acms-admin-icon-delete"
        onClick={context.onClose}
        aria-label={ACMS.i18n('modal.close')}
      />
    </header>
  );
});

Header.displayName = 'Modal.Header';

interface ModalBodyProps extends React.HTMLAttributes<HTMLDivElement> {
  tabContentScrollable?: boolean;
  children?: React.ReactNode;
}

const Body = forwardRef<HTMLDivElement, ModalBodyProps>(
  ({ children, className, tabContentScrollable = false, ...props }, ref) => (
    <div
      ref={ref}
      className={classnames('acms-admin-modal-body', className, {
        'acms-admin-modal-body-tab-scrollable': tabContentScrollable,
      })}
      {...props}
    >
      {children}
    </div>
  )
);

Body.displayName = 'Modal.Body';

interface ModalFooterProps extends React.HTMLAttributes<HTMLElement> {
  children?: React.ReactNode;
}

const Footer = forwardRef<HTMLDivElement, ModalFooterProps>(({ children, className, ...props }, ref) => (
  <footer ref={ref} className={classnames('acms-admin-modal-footer', className)} {...props}>
    {children}
  </footer>
));

Footer.displayName = 'Modal.Footer';

export default Object.assign(Modal, { Header, Body, Footer });
