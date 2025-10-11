import { forwardRef, Fragment, useCallback, useState } from 'react';
import {
  Menu,
  MenuTrigger,
  MenuGroup,
  MenuItem,
  MenuList as MenuItems,
  MenuPopover,
  MenuItemSelectEvent,
} from '../../../dropdown-menu';
import HStack from '../../../stack/h-stack';
import {
  type Menu as MenuType,
  isDropdownMenu,
  type DropdownMenu as DropdownMenuType,
  type DropdownMenuGroup,
  ModalMenu,
  DrawerMenu,
  isModalMenu,
  isDrawerMenu,
  RenderDisclosureProps,
  isDropdownMenuGroup,
  isMenu,
} from '../../types';
import Link from '../ui/link';
import Button from '../ui/button';

interface MenuListProps<T> {
  menus?: MenuType<T>[];
  data: T[];
}

interface ModalMenuButtonProps<T> extends Omit<React.HTMLAttributes<HTMLButtonElement>, 'onSelect'> {
  menu: ModalMenu<T>;
  data: T[];
  onSelect?: (menu: MenuType<T>) => void;
}

const ModalMenuButtonWithoutRef = <T,>(
  { menu, data, onSelect, onClick, ...props }: ModalMenuButtonProps<T>,
  ref: React.ForwardedRef<HTMLButtonElement>
) => {
  const handleButtonClick = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      onClick?.(event);
      onSelect?.(menu);
    },
    [onSelect, onClick, menu]
  );

  return (
    <Button
      {...props}
      ref={ref}
      {...(typeof menu.buttonProps === 'function' ? menu.buttonProps(data) : menu.buttonProps)}
      type="button"
      onClick={handleButtonClick}
    >
      {typeof menu.label === 'function' ? menu.label(data) : menu.label}
    </Button>
  );
};

ModalMenuButtonWithoutRef.displayName = 'ModalMenuButton';

const ModalMenuButton = forwardRef(ModalMenuButtonWithoutRef) as <T>({
  menu,
  data,
}: ModalMenuButtonProps<T> & { ref?: React.ForwardedRef<HTMLButtonElement> }) => JSX.Element;

interface DrawerMenuButtonProps<T> extends Omit<React.HTMLAttributes<HTMLButtonElement>, 'onSelect'> {
  menu: DrawerMenu<T>;
  data: T[];
  onSelect?: (menu: MenuType<T>) => void;
}

const DrawerMenuButtonWithoutRef = <T,>(
  { menu, data, onSelect, onClick, ...props }: DrawerMenuButtonProps<T>,
  ref: React.ForwardedRef<HTMLButtonElement>
) => {
  const handleButtonClick = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      onClick?.(event);
      onSelect?.(menu);
    },
    [onSelect, onClick, menu]
  );

  return (
    <Button
      {...props}
      ref={ref}
      {...(typeof menu.buttonProps === 'function' ? menu.buttonProps(data) : menu.buttonProps)}
      type="button"
      onClick={handleButtonClick}
    >
      {typeof menu.label === 'function' ? menu.label(data) : menu.label}
    </Button>
  );
};

DrawerMenuButtonWithoutRef.displayName = 'DrawerMenuButton';

const DrawerMenuButton = forwardRef(DrawerMenuButtonWithoutRef) as <T>({
  menu,
  data,
}: DrawerMenuButtonProps<T> & { ref?: React.ForwardedRef<HTMLButtonElement> }) => JSX.Element;

interface MenuRendererProps<T> {
  menu: Exclude<MenuType<T>, DropdownMenuType<T>>;
  data: T[];
  onSelect?: (menu: MenuType<T>) => void;
}

function renderMenuItem<T>({ menu, data, onSelect }: MenuRendererProps<T>) {
  if ('renderCustomMenu' in menu) {
    return menu.renderCustomMenu({ data });
  }
  if ('renderModal' in menu) {
    return <ModalMenuButton menu={menu} data={data} onSelect={onSelect} />;
  }

  if ('renderDrawer' in menu) {
    return <DrawerMenuButton menu={menu} data={data} onSelect={onSelect} />;
  }

  if ('getHref' in menu) {
    return (
      <Link
        {...(typeof menu.linkProps === 'function' ? menu.linkProps(data) : menu.linkProps)}
        href={menu.getHref(data)}
      >
        {typeof menu.label === 'function' ? menu.label(data) : menu.label}
      </Link>
    );
  }

  return (
    <Button
      type="button"
      {...(typeof menu.buttonProps === 'function' ? menu.buttonProps(data) : menu.buttonProps)}
      onClick={() => menu.onAction?.(data)}
    >
      {typeof menu.label === 'function' ? menu.label(data) : menu.label}
    </Button>
  );
}

function isActiveMenuGroup<T>(group: DropdownMenuGroup<T>, data: T[]): boolean {
  // eslint-disable-next-line @typescript-eslint/no-use-before-define
  if (group.menus.every((menu) => !isActiveMenu(menu, data))) {
    return false;
  }
  if (typeof group.condition === 'function' && !group.condition(data)) {
    return false;
  }
  return true;
}

function isActiveDropdownMenu<T>(menu: DropdownMenuType<T>, data: T[]): boolean {
  return menu.menus.some((menuOrGroup) => {
    if ('title' in menuOrGroup) {
      if (isActiveMenuGroup(menuOrGroup, data)) {
        return true;
      }

      return false;
    }
    // eslint-disable-next-line @typescript-eslint/no-use-before-define
    if (isActiveMenu(menuOrGroup, data)) {
      return true;
    }
    return false;
  });
}

function isActiveMenu<T>(menu: MenuType<T>, data: T[]): boolean {
  if (isDropdownMenu(menu) && !isActiveDropdownMenu(menu, data)) {
    return false;
  }

  if (typeof menu.condition === 'function' && !menu.condition(data)) {
    return false;
  }

  return true;
}

function findMenuById<T>(menus: MenuType<T>[], id: string): MenuType<T> | null {
  for (const menu of menus) {
    if (menu.id === id) {
      return menu;
    }
    if (isDropdownMenu(menu) || isDropdownMenuGroup<T>(menu)) {
      const found = findMenuById<T>(menu.menus as MenuType<T>[], id);
      if (found) {
        return found;
      }
    }
  }
  return null; // 見つからなかった場合
}

interface DropdownMenuProps<T> {
  menu: DropdownMenuType<T>;
  data: T[];
  onSelect?: (menu: MenuType<T>) => void;
}

const DropdownMenu = <T,>({ menu, data, onSelect }: DropdownMenuProps<T>) => {
  const activeMenus = menu.menus.filter((menuOrGroup) => {
    if ('title' in menuOrGroup) {
      return isActiveMenuGroup(menuOrGroup, data);
    }
    return isActiveMenu(menuOrGroup, data);
  });

  const handleSelect = useCallback(
    (event: MenuItemSelectEvent) => {
      if (onSelect) {
        const selectedMenu = findMenuById(menu.menus as MenuType<T>[], event.detail.value);
        if (selectedMenu) {
          onSelect(selectedMenu as MenuType<T>);
        }
      }
    },
    [onSelect, menu.menus]
  );

  return (
    <Menu>
      <MenuTrigger
        type="button"
        {...(typeof menu.buttonProps === 'function' ? menu.buttonProps(data) : menu.buttonProps)}
      >
        {typeof menu.label === 'function' ? menu.label(data) : menu.label}
      </MenuTrigger>
      <MenuPopover>
        <MenuItems>
          {activeMenus.map((menuOrGroup) => {
            if ('title' in menuOrGroup) {
              return (
                <MenuGroup
                  key={menuOrGroup.id}
                  title={typeof menuOrGroup.title === 'function' ? menuOrGroup.title(data) : menuOrGroup.title}
                >
                  {menuOrGroup.menus
                    .filter((menu) => isActiveMenu(menu, data))
                    .map((menu) => (
                      <MenuItem key={menu.id} value={menu.id} onSelect={handleSelect} asChild>
                        {renderMenuItem({ menu, data })}
                      </MenuItem>
                    ))}
                </MenuGroup>
              );
            }
            return (
              <MenuItem key={menuOrGroup.id} value={menuOrGroup.id} onSelect={handleSelect} asChild>
                {renderMenuItem({ menu: menuOrGroup, data })}
              </MenuItem>
            );
          })}
        </MenuItems>
      </MenuPopover>
    </Menu>
  );
};

function isDisclosableMenu<T>(menu: unknown): menu is ModalMenu<T> | DrawerMenu<T> {
  return isModalMenu(menu) || isDrawerMenu(menu);
}

function flattenMenus<T>(menus: MenuType<T>[]): MenuType<T>[] {
  const result: MenuType<T>[] = [];

  function traverse(items: MenuType<T>[]) {
    for (const item of items) {
      if (isMenu(item)) {
        // DropdownMenuGroup はフラットにする必要がないので、ここでは追加しない
        result.push(item);
      }
      if ('menus' in item && Array.isArray(item.menus)) {
        traverse(item.menus as MenuType<T>[]);
      }
    }
  }

  traverse(menus);
  return result;
}

interface MenuDisclosureProps<T> extends RenderDisclosureProps<T> {
  menu: ModalMenu<T> | DrawerMenu<T>;
}

const MenuDisclosure = <T,>({ isOpen, close, menu, data }: MenuDisclosureProps<T>) => {
  if (isModalMenu(menu)) {
    return menu.renderModal({ data, isOpen, close });
  }
  if (isDrawerMenu(menu)) {
    return menu.renderDrawer({ data, isOpen, close });
  }

  return null;
};

const MenuList = <T,>({ menus = [], data }: MenuListProps<T>) => {
  const [selectedDisclosableMenu, setSelectedDisclosableMenu] = useState<ModalMenu<T> | DrawerMenu<T> | null>(null);
  const activeMenus = menus.filter((menu) => isActiveMenu(menu, data));

  const disclosableMenus = flattenMenus(menus).filter((menu) => isDisclosableMenu(menu));

  const closeDisclosure = useCallback(() => {
    setSelectedDisclosableMenu(null);
  }, []);

  const handleSelect = useCallback((menu: MenuType<T>) => {
    if (isDisclosableMenu(menu)) {
      setSelectedDisclosableMenu(menu);
    }
  }, []);

  if (activeMenus.length === 0) {
    return null;
  }

  return (
    <>
      <HStack>
        {activeMenus.map((menu) => {
          if (isDropdownMenu(menu)) {
            return <DropdownMenu key={menu.id} menu={menu} data={data} onSelect={handleSelect} />;
          }
          return <Fragment key={menu.id}>{renderMenuItem({ menu, data, onSelect: handleSelect })}</Fragment>;
        })}
      </HStack>
      {disclosableMenus.map((menu) => (
        <MenuDisclosure
          key={menu.id}
          isOpen={selectedDisclosableMenu?.id === menu.id}
          close={closeDisclosure}
          menu={menu}
          data={data}
        />
      ))}
    </>
  );
};

export default MenuList;
