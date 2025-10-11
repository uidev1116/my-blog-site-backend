import { useCallback, useEffect, useRef } from 'react';
import useUpdateEffect from '@hooks/use-update-effect';
import useModalManager, { UseModalManagerOptions } from './store/use-modal-manager';
import Modal from '../../components/modal/modal';
import { datasetToProps } from '../../utils/react';

type ModalProps = React.ComponentProps<typeof Modal>;
type ModalHeaderProps = React.ComponentProps<typeof Modal.Header>;
type ModalBodyProps = React.ComponentProps<typeof Modal.Body>;
type ModalFooterProps = React.ComponentProps<typeof Modal.Footer>;

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface ModalControllerProps extends UseModalManagerOptions {}

/**
 * data-modal-*属性を動的に取得する関数
 */
function parseProps<T extends 'modal' | 'modal-header' | 'modal-body' | 'modal-footer'>(
  element: HTMLElement,
  type: T
): Partial<
  T extends 'modal'
    ? ModalProps
    : T extends 'modal-header'
      ? ModalHeaderProps
      : T extends 'modal-body'
        ? ModalBodyProps
        : ModalFooterProps
> {
  const props = datasetToProps(element.dataset, type);
  return props as Partial<
    T extends 'modal'
      ? ModalProps
      : T extends 'modal-header'
        ? ModalHeaderProps
        : T extends 'modal-body'
          ? ModalBodyProps
          : ModalFooterProps
  >;
}

const ModalController = ({ ...props }: ModalControllerProps) => {
  const { manager, state } = useModalManager(props);
  const openStateRef = useRef<boolean>(state.modalProps.isOpen);

  const hydrateModalContent = useCallback(() => {
    if (manager.container) {
      ACMS.Dispatch(manager.container);
      ACMS.dispatchEvent('acmsDialogOpened', manager.container, {
        item: manager.container,
      });
    }
  }, [manager.container]);

  const handleAfterOpen = useCallback(() => {
    openStateRef.current = true;
    hydrateModalContent();
  }, [hydrateModalContent]);

  const handleAfterClose = useCallback(() => {
    openStateRef.current = false;
  }, []);

  useEffect(() => {
    const handleOpen = (event: Event) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }

      const element = event.target.closest<HTMLElement>(ModalController.openTrigger);

      if (!element) {
        return;
      }

      const { target: selector, url } = element.dataset;

      // Modal用 props とセレクタ情報を data-* から取得
      const modalProps = parseProps(element, 'modal');
      const modalHeaderProps = parseProps(element, 'modal-header');
      const modalBodyProps = parseProps(element, 'modal-body');
      const modalFooterProps = parseProps(element, 'modal-footer');

      // ModalManagerでモーダルを開く
      manager.open({
        selector,
        url,
        modalProps,
        modalHeaderProps,
        modalBodyProps,
        modalFooterProps,
      });
    };

    const handleClose = (event: Event) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }

      const element = event.target.closest<HTMLElement>(ModalController.closeTrigger);

      if (!element) {
        return;
      }

      manager.close();
    };
    document.addEventListener('click', handleOpen);
    document.addEventListener('click', handleClose);

    return () => {
      document.removeEventListener('click', handleOpen);
      document.removeEventListener('click', handleClose);
    };
  }, [manager]);

  useUpdateEffect(() => {
    if (!openStateRef.current) {
      // モーダルが開いた初回はonAfterOpenでhydrateするためスキップ
      return;
    }

    hydrateModalContent();
    // コンテンツが変更されたら組み込みJSを実行
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.content.raw]);

  if (!manager.container) {
    return null;
  }

  return (
    <Modal
      {...state.modalProps}
      onClose={manager.close}
      container={manager.container}
      onAfterOpen={handleAfterOpen}
      onAfterClose={handleAfterClose}
    >
      {state.content.header && (
        <Modal.Header {...state.modalHeaderProps}>
          {/* eslint-disable-next-line react/no-danger */}
          <span dangerouslySetInnerHTML={{ __html: state.content.header }} />
        </Modal.Header>
      )}
      <Modal.Body {...state.modalBodyProps}>
        {/* eslint-disable-next-line react/no-danger */}
        <div dangerouslySetInnerHTML={{ __html: state.content.body || state.content.raw }} />
      </Modal.Body>
      {state.content.footer && (
        <Modal.Footer {...state.modalFooterProps}>
          {/* eslint-disable-next-line react/no-danger */}
          <div dangerouslySetInnerHTML={{ __html: state.content.footer }} />
        </Modal.Footer>
      )}
    </Modal>
  );
};

ModalController.openTrigger = '.js-acms-modal-open';
ModalController.closeTrigger = '.js-acms-modal-close';

export default ModalController;
