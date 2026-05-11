import { useCallback, useEffect, useRef } from 'react';
import useUpdateEffect from '@hooks/use-update-effect';
import useDrawerManager, { UseDrawerManagerOptions } from './store/use-drawer-manager';
import Drawer from '../../components/drawer/drawer';
import { datasetToProps } from '../../utils/react';

type DrawerProps = React.ComponentProps<typeof Drawer>;
type DrawerHeaderProps = React.ComponentProps<typeof Drawer.Header>;
type DrawerBodyProps = React.ComponentProps<typeof Drawer.Body>;
type DrawerFooterProps = React.ComponentProps<typeof Drawer.Footer>;

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface DrawerControllerProps extends UseDrawerManagerOptions {}

/**
 * data-drawer-*属性を動的に取得する関数
 */
function parseProps<T extends 'drawer' | 'drawer-header' | 'drawer-body' | 'drawer-footer'>(
  element: HTMLElement,
  type: T
): Partial<
  T extends 'drawer'
    ? DrawerProps
    : T extends 'drawer-header'
      ? DrawerHeaderProps
      : T extends 'drawer-body'
        ? DrawerBodyProps
        : DrawerFooterProps
> {
  const props = datasetToProps(element.dataset, type);
  return props as Partial<
    T extends 'drawer'
      ? DrawerProps
      : T extends 'drawer-header'
        ? DrawerHeaderProps
        : T extends 'drawer-body'
          ? DrawerBodyProps
          : DrawerFooterProps
  >;
}

const DrawerController = ({ ...props }: DrawerControllerProps) => {
  const { manager, state } = useDrawerManager(props);
  const openStateRef = useRef<boolean>(state.drawerProps.isOpen);

  const hydrateDrawerContent = useCallback(() => {
    if (manager.container) {
      ACMS.Dispatch(manager.container);
      ACMS.dispatchEvent('acmsDialogOpened', manager.container, {
        item: manager.container,
      });
    }
  }, [manager.container]);

  const handleAfterOpen = useCallback(() => {
    openStateRef.current = true;
    hydrateDrawerContent();
  }, [hydrateDrawerContent]);

  const handleAfterClose = useCallback(() => {
    openStateRef.current = false;
    // 閉じるトランジション完了後に state をクリアする
    manager.reset();
  }, [manager]);

  useEffect(() => {
    const handleOpen = (event: Event) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }

      const element = event.target.closest<HTMLElement>(DrawerController.openTrigger);

      if (!element) {
        return;
      }

      const { target: selector, url } = element.dataset;

      // Drawer用 props とセレクタ情報を data-* から取得
      const drawerProps = parseProps(element, 'drawer');
      const drawerHeaderProps = parseProps(element, 'drawer-header');
      const drawerBodyProps = parseProps(element, 'drawer-body');
      const drawerFooterProps = parseProps(element, 'drawer-footer');

      // DrawerManagerでドロワーを開く
      manager.open({
        selector,
        url,
        drawerProps,
        drawerHeaderProps,
        drawerBodyProps,
        drawerFooterProps,
      });
    };

    const handleClose = (event: Event) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }

      const element = event.target.closest<HTMLElement>(DrawerController.closeTrigger);

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
      // ドロワーが開いた初回はonAfterOpenでhydrateするためスキップ
      return;
    }

    hydrateDrawerContent();
    // コンテンツが変更されたら組み込みJSを実行
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.content.raw]);

  if (!manager.container) {
    return null;
  }

  return (
    <Drawer
      {...state.drawerProps}
      onClose={manager.close}
      container={manager.container}
      onAfterOpen={handleAfterOpen}
      onAfterClose={handleAfterClose}
    >
      {state.content.header && (
        <Drawer.Header {...state.drawerHeaderProps}>
          {/* eslint-disable-next-line react/no-danger */}
          <span dangerouslySetInnerHTML={{ __html: state.content.header }} />
        </Drawer.Header>
      )}
      <Drawer.Body {...state.drawerBodyProps}>
        {/* eslint-disable-next-line react/no-danger */}
        <div dangerouslySetInnerHTML={{ __html: state.content.body || state.content.raw }} />
      </Drawer.Body>
      {state.content.footer && (
        <Drawer.Footer {...state.drawerFooterProps}>
          {/* eslint-disable-next-line react/no-danger */}
          <div dangerouslySetInnerHTML={{ __html: state.content.footer }} />
        </Drawer.Footer>
      )}
    </Drawer>
  );
};

DrawerController.openTrigger = '.js-acms-drawer-open';
DrawerController.closeTrigger = '.js-acms-drawer-close';

export default DrawerController;
