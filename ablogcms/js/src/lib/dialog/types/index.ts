import Modal from '../../../components/modal/modal';

export type DialogType = 'confirm' | 'alert' | 'prompt';

export type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  label: React.ReactNode;
  icon?: React.ReactNode;
};

export interface DialogStoreInterface {
  type: string;
  isOpen: true;
  onClose: () => void;
  title?: string;
  message: React.ReactNode;
  buttons?: ButtonProps[];
  body?: (props: DialogStoreInterface) => React.ReactNode;
  dialogProps?: DialogProps;
}

export interface ConfirmDialogStore extends DialogStoreInterface {
  type: 'confirm';
}

export interface AlertDialogStore extends DialogStoreInterface {
  type: 'alert';
}

export interface PromptDialogStore extends DialogStoreInterface {
  type: 'prompt';
}

export type DialogProps = Omit<React.ComponentPropsWithoutRef<typeof Modal>, 'children' | 'onClose' | 'isOpen'>;

export type DialogStore = ConfirmDialogStore | AlertDialogStore | PromptDialogStore;
