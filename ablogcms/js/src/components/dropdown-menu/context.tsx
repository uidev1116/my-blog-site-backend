import { createContext, useContext } from 'react';
import type { MenuItemValueChangeEvent } from './events';
import useMenu from './use-menu';

// Menu階層のコンテキスト
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface MenuContextType extends ReturnType<typeof useMenu> {
  id: string;
  closeOnSelect: boolean;
  triggerRef: React.RefObject<HTMLButtonElement>;
  menuPopoverRef: React.RefObject<HTMLElement>;
  menuListRef: React.RefObject<HTMLElement>;
  menuItemRefs: React.MutableRefObject<HTMLElement[]>;
  parentMenu: MenuContextType | undefined;
  elevation: number;
}

// Context to handle menu state
export const MenuContext = createContext<MenuContextType | undefined>(undefined);

// 現在のメニューのコンテキストを取得
export const useMenuContext = () => {
  const context = useContext(MenuContext);
  if (!context) {
    throw new Error('useMenuContext must be used within a MenuProvider');
  }
  return context;
};

// 親メニューのコンテキストを取得（存在しない場合はundefined）
export const useParentMenuContext = (): MenuContextType | undefined => {
  try {
    return useMenuContext();
  } catch {
    return undefined;
  }
};

// Context to handle menu state
export const MenuRadioGroupContext = createContext<
  | {
      value: string;
      setValue: (value: string) => void;
      onValueChange?: (event: MenuItemValueChangeEvent) => void;
    }
  | undefined
>(undefined);

export const useMenuRadioGroupContext = () => {
  const context = useContext(MenuRadioGroupContext);
  if (!context) {
    throw new Error('useMenuRadioGroupContext must be used within a MenuRadioGroupProvider');
  }
  return context;
};
