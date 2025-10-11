import { useMemo } from 'react';
import { Editor } from '@tiptap/react';
import type { BlockMenuItem, CommandItem } from '@features/block-editor/types';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Menu, MenuTrigger, MenuList, MenuItem, MenuGroup, MenuPopover } from '@components/dropdown-menu';
import { useFilteredCommands } from '@features/block-editor/hooks/useBlockMenus';

export type ContentPickerProps = {
  menus: BlockMenuItem[];
  editor: Editor;
  currentPos: number;
};

export const ContentTypePicker = ({ menus, editor, currentPos }: ContentPickerProps) => {
  const activeItem = useMemo(() => {
    return menus
      .flatMap((item) => item.commands)
      .find((cmd) => (cmd.isActive?.(editor) ?? false) && cmd.isTextMenu === true);
  }, [menus, editor]);

  const filteredCommands = useFilteredCommands(menus, editor, currentPos);

  return (
    <Menu>
      {activeItem && (
        <>
          <MenuTrigger asChild>
            <Toolbar.Button type="button" tooltip="ブロックタイプ" aria-label="ブロックタイプを選択">
              <Icon name={activeItem.iconName} />
              {activeItem.label}
              <Icon name="keyboard_arrow_down" />
            </Toolbar.Button>
          </MenuTrigger>
          <Toolbar.Divider />
        </>
      )}
      <MenuPopover scrollable data-elevation="3">
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
  );
};
