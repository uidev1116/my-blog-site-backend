import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { Icon } from '@features/block-editor/components/ui/Icon';
import {
  Menu,
  MenuTrigger,
  MenuList,
  MenuGroup,
  MenuItemRadio,
  MenuItemRadioGroup,
  MenuPopover,
} from '@components/dropdown-menu';

export type AlignPickerProps = {
  align: 'left' | 'center' | 'right';
  onChange: (align: 'left' | 'center' | 'right') => void;
};

export const AlignPicker = ({ align, onChange }: AlignPickerProps) => {
  return (
    <Menu closeOnSelect>
      <MenuTrigger asChild>
        <Toolbar.Button type="button" tooltip="配置" aria-label="配置を設定">
          {align === 'left' && <Icon name="format_align_left" />}
          {align === 'center' && <Icon name="format_align_center" />}
          {align === 'right' && <Icon name="format_align_right" />}
          <Icon name="keyboard_arrow_down" />
        </Toolbar.Button>
      </MenuTrigger>
      <MenuPopover size="medium" data-elevation="3">
        <MenuList>
          <MenuGroup title="配置">
            <MenuItemRadioGroup
              defaultValue={align}
              onValueChange={(event) => {
                onChange(event.detail.value as 'left' | 'center' | 'right');
              }}
            >
              <MenuItemRadio key="align-left" value="left">
                <Icon name="format_align_left" />
                左揃え
              </MenuItemRadio>
              <MenuItemRadio key="align-center" value="center">
                <Icon name="format_align_center" />
                中央揃え
              </MenuItemRadio>
              <MenuItemRadio key="align-right" value="right">
                <Icon name="format_align_right" />
                右揃え
              </MenuItemRadio>
            </MenuItemRadioGroup>
          </MenuGroup>
        </MenuList>
      </MenuPopover>
    </Menu>
  );
};
