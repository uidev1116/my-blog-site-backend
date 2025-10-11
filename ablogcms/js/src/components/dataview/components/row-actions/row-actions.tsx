import { Fragment, useCallback, useMemo, useState } from 'react';
import { Menu, MenuItem, MenuTrigger, MenuList, MenuDivider, MenuPopover } from '../../../dropdown-menu';
import type { Action, ModalAction, RenderModalProps } from '../../types';
import Link from '../ui/link';
import Button from '../ui/button';

interface RowActionsProps<T> {
  actions: Action<T>[];
  data: T;
}

interface ModalActionButtonProps<T> extends Omit<React.HTMLAttributes<HTMLElement>, 'onSelect'> {
  action: ModalAction<T>;
  data: T;
  onSelect?: (action: Action<T>) => void;
}

const ModalActionButton = <T,>({ action, data, onSelect, onClick, ...props }: ModalActionButtonProps<T>) => {
  const handleButtonClick = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      onClick?.(event);
      onSelect?.(action);
    },
    [onSelect, onClick, action]
  );

  return (
    <Button
      {...props}
      {...(typeof action.buttonProps === 'function' ? action.buttonProps(data) : action.buttonProps)}
      type="button"
      onClick={handleButtonClick}
    >
      {typeof action.label === 'function' ? action.label(data) : action.label}
    </Button>
  );
};

interface ActionModalProps<T> extends RenderModalProps<T> {
  action: ModalAction<T>;
}

const ActionModal = <T,>({ isOpen, close, action, data }: ActionModalProps<T>) => {
  return action.renderModal({ data, isOpen, close });
};

const RowActions = <T,>({ actions, data }: RowActionsProps<T>) => {
  const activeActions = useMemo(
    () => actions.filter((action) => action.condition === undefined || action.condition(data)),
    [actions, data]
  );

  const primaryActions = useMemo(() => activeActions.filter((action) => action.type === 'primary'), [activeActions]);
  const secondaryActions = useMemo(
    () => activeActions.filter((action) => action.type === 'secondary' || action.type === undefined),
    [activeActions]
  );
  const tertiaryActions = useMemo(() => activeActions.filter((action) => action.type === 'tertiary'), [activeActions]);

  const renderAction = useCallback(
    (action: Action<T>) => {
      if ('renderModal' in action) {
        return <ModalActionButton action={action} data={data} />;
      }

      if ('getHref' in action) {
        return (
          <Link
            {...(typeof action.linkProps === 'function' ? action.linkProps(data) : action.linkProps)}
            href={action.getHref(data)}
          >
            {typeof action.label === 'function' ? action.label(data) : action.label}
          </Link>
        );
      }

      return (
        <Button
          type="button"
          {...(typeof action.buttonProps === 'function' ? action.buttonProps(data) : action.buttonProps)}
          onClick={() => action.onAction?.(data)}
        >
          {typeof action.label === 'function' ? action.label(data) : action.label}
        </Button>
      );
    },
    [data]
  );

  const [selectedModalAction, setSelectedModalAction] = useState<ModalAction<T> | null>(null);

  const modalActions = useMemo(() => activeActions.filter((action) => 'renderModal' in action), [activeActions]);

  const closeModal = useCallback(() => {
    setSelectedModalAction(null);
  }, []);

  const handleSelect = useCallback((action: Action<T>) => {
    if ('renderModal' in action) {
      setSelectedModalAction(action);
    }
  }, []);

  return (
    <>
      <div className="acms-admin-dataview-row-actions">
        <div className="acms-admin-btn-group" role="group">
          <Menu>
            {primaryActions.map((action) => (
              <Fragment key={action.id}>{renderAction(action)}</Fragment>
            ))}
            {secondaryActions.length > 0 && (
              <>
                <MenuTrigger className="acms-admin-btn-admin">
                  <span className="acms-admin-arrow-bottom" aria-hidden />
                </MenuTrigger>
                <MenuPopover size="small">
                  <MenuList>
                    {secondaryActions.map((action, index) => (
                      <Fragment key={action.id}>
                        {index > 0 && <MenuDivider />}
                        <MenuItem asChild onSelect={() => handleSelect(action)}>
                          {renderAction(action)}
                        </MenuItem>
                      </Fragment>
                    ))}
                  </MenuList>
                </MenuPopover>
              </>
            )}
          </Menu>
        </div>
        {tertiaryActions.length > 0 && (
          <div className="acms-admin-dataview-row-actions-tertiary">
            {tertiaryActions.map((action) => (
              <Fragment key={action.id}>{renderAction(action)}</Fragment>
            ))}
          </div>
        )}
      </div>
      {modalActions.map((action) => (
        <ActionModal
          key={action.id}
          isOpen={selectedModalAction?.id === action.id}
          close={closeModal}
          action={action}
          data={data}
        />
      ))}
    </>
  );
};

export default RowActions;
