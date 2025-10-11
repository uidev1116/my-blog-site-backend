import React, { useCallback, useEffect, useRef, useState } from 'react';
import type { CommandItem, BlockMenuProps } from '@features/block-editor/types';
import { Icon } from '../../components/ui/Icon';
import { Menu, MenuGroup, MenuItem, MenuList, MenuPopover } from '../../../../components/dropdown-menu';

interface BlockMenuRef {
  onKeyDown: (event: { event: KeyboardEvent }) => boolean;
}

const BlockMenu = React.forwardRef<BlockMenuRef, BlockMenuProps>(({ items, command, ...menuProps }, ref) => {
  const scrollContainer = useRef<HTMLDivElement>(null);
  const itemRefs = useRef<Array<Array<HTMLDivElement | null>>>([]);
  const [selectedGroupIndex, setSelectedGroupIndex] = useState(0);
  const [selectedCommandIndex, setSelectedCommandIndex] = useState(0);

  // Anytime the groups change, i.e. the user types to narrow it down, we want to
  // reset the current selection to the first menu item
  useEffect(() => {
    itemRefs.current = items.map((group) => new Array(group.commands.length).fill(null));
    setSelectedGroupIndex(0);
    setSelectedCommandIndex(0);
  }, [items]);

  const selectItem = useCallback(
    (groupIndex: number, commandIndex: number) => {
      const selectedCommand = items[groupIndex].commands[commandIndex];
      command(selectedCommand);
    },
    [items, command]
  );

  React.useImperativeHandle(ref, () => ({
    onKeyDown: ({ event }: { event: KeyboardEvent }) => {
      if (event.key === 'ArrowDown') {
        if (!items.length) {
          return false;
        }

        const { commands } = items[selectedGroupIndex];

        let newCommandIndex = selectedCommandIndex + 1;
        let newGroupIndex = selectedGroupIndex;

        if (commands.length - 1 < newCommandIndex) {
          newCommandIndex = 0;
          newGroupIndex = selectedGroupIndex + 1;
        }

        if (items.length - 1 < newGroupIndex) {
          newGroupIndex = 0;
        }

        setSelectedCommandIndex(newCommandIndex);
        setSelectedGroupIndex(newGroupIndex);

        return true;
      }

      if (event.key === 'ArrowUp') {
        if (!items.length) {
          return false;
        }

        let newCommandIndex = selectedCommandIndex - 1;
        let newGroupIndex = selectedGroupIndex;

        if (newCommandIndex < 0) {
          newGroupIndex = selectedGroupIndex - 1;
          const commandLength = items[newGroupIndex]?.commands?.length || 0;
          newCommandIndex = commandLength > 0 ? commandLength - 1 : 0;
        }

        if (newGroupIndex < 0) {
          newGroupIndex = items.length - 1;
          newCommandIndex = items[newGroupIndex].commands.length - 1;
        }

        setSelectedCommandIndex(newCommandIndex);
        setSelectedGroupIndex(newGroupIndex);

        return true;
      }

      if (event.key === 'Enter') {
        if (!items.length || selectedGroupIndex === -1 || selectedCommandIndex === -1) {
          return false;
        }

        selectItem(selectedGroupIndex, selectedCommandIndex);

        return true;
      }

      return false;
    },
  }));

  useEffect(() => {
    const el = itemRefs.current[selectedGroupIndex]?.[selectedCommandIndex];
    if (el) {
      el.scrollIntoView({
        block: 'nearest',
        behavior: 'auto',
      });
    }
  }, [selectedCommandIndex, selectedGroupIndex]);

  const createCommandClickHandler = useCallback(
    (groupIndex: number, commandIndex: number) => () => {
      selectItem(groupIndex, commandIndex);
    },
    [selectItem]
  );

  if (!items.length) {
    return null;
  }

  return (
    <Menu
      {...menuProps}
      // フォーカスはエディタ上においたままにしたいので、
      // メニューを開いたときに、フォーカスを最初のアイテムに設定する機能を無効にする
      focusManagerOptions={{ initialFocus: -1 }}
      // 独自でフォーカス管理を実装したいので、デフォルトのフォーカス管理を無効にする
      listNavigationOptions={{ enabled: false }}
    >
      <MenuPopover ref={scrollContainer} scrollable data-elevation="3">
        <MenuList>
          {items.map((group, groupIndex: number) => (
            <MenuGroup key={`${group.title}-group`} title={group.title}>
              {group.commands.map((command: CommandItem, commandIndex: number) => (
                <MenuItem
                  key={`${command.label}`}
                  ref={(el) => {
                    if (!itemRefs.current[groupIndex]) {
                      itemRefs.current[groupIndex] = [];
                    }
                    itemRefs.current[groupIndex][commandIndex] = el;
                  }}
                  isActive={selectedGroupIndex === groupIndex && selectedCommandIndex === commandIndex}
                  onSelect={createCommandClickHandler(groupIndex, commandIndex)}
                  icon={<Icon name={command.iconName} />}
                >
                  {command.label}
                </MenuItem>
              ))}
            </MenuGroup>
          ))}
        </MenuList>
      </MenuPopover>
    </Menu>
  );
});

BlockMenu.displayName = 'BlockMenu';

export default BlockMenu;
