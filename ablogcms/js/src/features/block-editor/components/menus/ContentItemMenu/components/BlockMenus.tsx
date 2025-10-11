import { Editor } from '@tiptap/react';
import type { BlockMenuItem, CommandItem } from '@features/block-editor/types';
import { Menu, SubmenuTriggerItem, MenuList, MenuItem, MenuGroup, MenuPopover } from '@components/dropdown-menu';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { useFilteredCommands } from '@features/block-editor/hooks/useBlockMenus';

export type BlockMenusProps = {
  menus: BlockMenuItem[];
  editor: Editor;
  currentPos: number;
};

export const BlockMenus = ({ menus, editor, currentPos }: BlockMenusProps) => {
  const filteredCommands = useFilteredCommands(menus, editor, currentPos);

  return filteredCommands.length > 0 ? (
    <Menu>
      <SubmenuTriggerItem icon={<Icon name="sync" />}>ブロックタイプ変換</SubmenuTriggerItem>
      <MenuPopover data-elevation="3">
        <MenuList>
          {filteredCommands.map((group) => (
            <MenuGroup title={group.title} key={group.title}>
              {group.commands.map((command: CommandItem) => (
                <MenuItem
                  key={`${command.name}_${command.label}`}
                  icon={<Icon name={command.iconName} />}
                  onSelect={command.convert ? () => command.convert!(editor, currentPos) : () => {}}
                >
                  {command.label}
                </MenuItem>
              ))}
            </MenuGroup>
          ))}
        </MenuList>
      </MenuPopover>
    </Menu>
  ) : null;
};
