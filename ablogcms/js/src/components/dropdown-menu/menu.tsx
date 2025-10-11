import { useRef, useMemo, useId } from 'react';
import { FloatingNode, FloatingTree, useFloatingParentNodeId } from '@floating-ui/react';
import { MenuContext, useParentMenuContext } from './context';
import type { MenuOptions } from './use-menu';
import useMenu from './use-menu';
// 公開props
interface MenuProps extends MenuOptions {
  children?: React.ReactNode;
  elevationStartLevel?: number;
}

const MenuRoot = ({ children, elevationStartLevel = 2, ...restOptions }: MenuProps) => {
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuPopoverRef = useRef<HTMLElement>(null);
  const menuListRef = useRef<HTMLElement>(null);
  const menuItemRefs = useRef<HTMLElement[]>([]);
  const id = useId();

  // // 親メニューのコンテキストを取得（存在しない場合はundefined）
  const parentMenu = useParentMenuContext();

  // // 親メニューが存在する場合、elevationを+1する。存在しない場合はelevationStartLevel
  const elevation = parentMenu ? parentMenu.elevation + 1 : elevationStartLevel;

  const menu = useMenu({ ...restOptions });
  const value = useMemo(
    () => ({
      id,
      ...menu,
      triggerRef,
      menuPopoverRef,
      menuListRef,
      menuItemRefs,
      parentMenu,
      elevation,
    }),
    [id, menu, triggerRef, menuPopoverRef, menuListRef, menuItemRefs, parentMenu, elevation]
  );

  return (
    <FloatingNode id={menu.nodeId}>
      <MenuContext.Provider value={value}>{children}</MenuContext.Provider>
    </FloatingNode>
  );
};

const Menu = (props: MenuProps) => {
  const parentId = useFloatingParentNodeId();

  if (parentId === null) {
    return (
      <FloatingTree>
        <MenuRoot {...props} />
      </FloatingTree>
    );
  }

  return <MenuRoot {...props} />;
};

Menu.displayName = 'Menu';

export default Menu;
