import classnames from 'classnames';
import HStack from '@components/stack/h-stack';
import Modal from '../../components/modal/modal';
import useDialogStore from './store/hook';

const DialogContainer = () => {
  const { snapshot } = useDialogStore();

  return (
    <Modal
      isOpen={snapshot.isOpen}
      onClose={snapshot.onClose}
      size="small"
      aria-labelledby="acms-dialog-title"
      aria-describedby="acms-dialog-description"
      {...(snapshot.isOpen ? snapshot.dialogProps : {})}
    >
      {snapshot.isOpen && (
        <>
          <Modal.Header>{snapshot.title}</Modal.Header>
          <Modal.Body>
            {typeof snapshot.body === 'function' ? (
              snapshot.body(snapshot)
            ) : (
              <p id="acms-dialog-description">{snapshot.message}</p>
            )}
          </Modal.Body>
          {snapshot.buttons && snapshot.buttons.length > 0 && (
            <Modal.Footer>
              <HStack display="inline-flex">
                {snapshot.buttons.map(({ label, className, icon, ...props }, index) => (
                  // eslint-disable-next-line react/no-array-index-key
                  <button key={index} className={classnames('acms-admin-btn', className)} type="button" {...props}>
                    {icon}
                    {label}
                  </button>
                ))}
              </HStack>
            </Modal.Footer>
          )}
        </>
      )}
    </Modal>
  );
};

export default DialogContainer;
