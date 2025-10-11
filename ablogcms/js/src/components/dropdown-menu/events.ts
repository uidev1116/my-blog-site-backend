interface MenuItemSelectEventDetail {
  value: string;
}

export type MenuItemSelectEvent = CustomEvent<MenuItemSelectEventDetail>;

export const MENU_ITEM_SELECT_EVENT = 'acms.dropdown-menu.select';

export function createMenuItemSelectEvent({ value }: MenuItemSelectEventDetail): MenuItemSelectEvent {
  return new CustomEvent(MENU_ITEM_SELECT_EVENT, {
    detail: { value },
  });
}

interface MenuItemCheckedChangeEventDetail {
  checked: boolean;
  value: string;
}

export type MenuItemCheckedChangeEvent = CustomEvent<MenuItemCheckedChangeEventDetail>;

export const MENU_ITEM_CHECKED_CHANGE_EVENT = 'acms.dropdown-menu.checked.change';

export function createMenuItemCheckedChangeEvent({
  checked,
  value,
}: MenuItemCheckedChangeEventDetail): MenuItemCheckedChangeEvent {
  return new CustomEvent(MENU_ITEM_CHECKED_CHANGE_EVENT, {
    detail: { checked, value },
  });
}

interface MenuItemValueChangeEventDetail {
  value: string;
}

export type MenuItemValueChangeEvent = CustomEvent<MenuItemValueChangeEventDetail>;

export const MENU_ITEM_VALUE_CHANGE_EVENT = 'acms.dropdown-menu.value.change';

export function createMenuItemValueChangeEvent({ value }: MenuItemValueChangeEventDetail): MenuItemValueChangeEvent {
  return new CustomEvent(MENU_ITEM_VALUE_CHANGE_EVENT, {
    detail: { value },
  });
}
