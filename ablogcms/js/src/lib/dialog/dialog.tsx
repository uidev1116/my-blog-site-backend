import VStack from '@components/stack/v-stack';
import { openDialog, closeDialog } from './store/store';
import { ButtonProps, DialogStore, DialogStoreInterface } from './types';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface DialogOptionsInterface extends Pick<DialogStoreInterface, 'title' | 'dialogProps'> {}

export interface ConfirmDialogOptions extends DialogOptionsInterface {
  confirmButton: Partial<ButtonProps>;
  cancelButton: Partial<ButtonProps>;
}

export interface AlertDialogOptions extends DialogOptionsInterface {
  confirmButton: Partial<ButtonProps>;
}

export interface PromptDialogOptions extends DialogOptionsInterface {
  defaultValue?: string;
  confirmButton: Partial<ButtonProps>;
  cancelButton: Partial<ButtonProps>;
}

function openConfirmDialog(
  message: DialogStore['message'],
  options: Partial<ConfirmDialogOptions> = {}
): Promise<boolean> {
  return new Promise((resolve) => {
    const handleConfirm = () => {
      closeDialog();
      resolve(true);
    };
    const handleClose = () => {
      closeDialog();
      resolve(false);
    };
    openDialog({
      isOpen: true,
      onClose: handleClose,
      type: 'confirm',
      message,
      buttons: [
        {
          id: 'acms-dialog-cancel-button',
          label: ACMS.i18n('dialog.cancel'),
          onClick: handleClose,
          ...options.cancelButton,
          type: 'button',
        },
        {
          id: 'acms-dialog-ok-button',
          label: ACMS.i18n('dialog.ok'),
          onClick: handleConfirm,
          className: 'acms-admin-btn-primary',
          ...options.confirmButton,
          type: 'button',
        },
      ],
      title: ACMS.i18n('dialog.confirm_title'),
      ...options,
      dialogProps: {
        focusTrapOptions: {
          initialFocus: '#acms-dialog-ok-button',
        },
        role: 'alertdialog',
        ...options.dialogProps,
      },
    });
  });
}

function openAlertDialog(message: DialogStore['message'], options: Partial<AlertDialogOptions> = {}): Promise<void> {
  return new Promise((resolve) => {
    const handleClose = () => {
      closeDialog();
      resolve();
    };
    openDialog({
      isOpen: true,
      onClose: handleClose,
      type: 'alert',
      message,
      buttons: [
        {
          id: 'acms-dialog-ok-button',
          label: ACMS.i18n('dialog.ok'),
          onClick: handleClose,
          className: 'acms-admin-btn-primary',
          ...options.confirmButton,
          type: 'button',
        },
      ],
      title: ACMS.i18n('dialog.warning_title'),
      ...options,
      dialogProps: {
        focusTrapOptions: {
          initialFocus: '#acms-dialog-ok-button',
        },
        role: 'alertdialog',
        ...options.dialogProps,
      },
    });
  });
}

function openPromptDialog(
  message: DialogStore['message'],
  options: Partial<PromptDialogOptions> = {}
): Promise<string | null> {
  return new Promise((resolve) => {
    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
      if (!(event.target instanceof HTMLFormElement)) {
        return;
      }
      event.preventDefault();
      const formData = new FormData(event.target);
      const value = formData.get('prompt') as string | null;
      closeDialog();
      resolve(value);
    };
    const handleClose = () => {
      closeDialog();
      resolve(null);
    };
    openDialog({
      isOpen: true,
      onClose: handleClose,
      body(store) {
        return (
          <form id="acms-dialog-prompt-form" onSubmit={handleSubmit} className="acms-admin-form">
            <VStack asChild align="start">
              <label htmlFor="input-text-dialog-prompt">
                <span id="acms-dialog-description">{store.message}</span>
                <input
                  id="input-text-dialog-prompt"
                  type="text"
                  name="prompt"
                  className="acms-admin-form-width-full"
                  defaultValue={options.defaultValue}
                />
              </label>
            </VStack>
          </form>
        );
      },
      type: 'prompt',
      message,
      buttons: [
        {
          id: 'acms-dialog-cancel-button',
          label: ACMS.i18n('dialog.cancel'),
          onClick: handleClose,
          ...options.cancelButton,
          type: 'button',
        },
        {
          id: 'acms-dialog-ok-button',
          label: ACMS.i18n('dialog.ok'),
          className: 'acms-admin-btn-primary',
          ...options.confirmButton,
          type: 'submit',
          form: 'acms-dialog-prompt-form',
        },
      ],
      title: ACMS.i18n('dialog.prompt_title'),
      ...options,
      dialogProps: {
        focusTrapOptions: {
          initialFocus: '#input-text-dialog-prompt',
        },
        ...options.dialogProps,
      },
    });
  });
}

const dialog = {
  confirm(...args: Parameters<typeof openConfirmDialog>) {
    return openConfirmDialog(...args);
  },
  alert(...args: Parameters<typeof openAlertDialog>) {
    return openAlertDialog(...args);
  },
  prompt(...args: Parameters<typeof openPromptDialog>) {
    return openPromptDialog(...args);
  },
};

export default dialog;
