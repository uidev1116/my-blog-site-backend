import { Editor } from '@tiptap/core';
import { Menu } from '../../../components/dropdown-menu';
import type { MaterialSymbol } from '../../../types/material-symbols';

export interface CommandItem {
  name: string;
  label: string;
  description: string;
  iconName: MaterialSymbol;
  isTextMenu: boolean;
  class?: string;
  aliases?: string[];
  action?: (editor: Editor) => void;
  convert?: (editor: Editor, pos: number) => void;
  isActive?: (editor: Editor) => boolean;
  isDisabled: (editor: Editor, pos: number) => boolean;
  shouldBeHidden?: (editor: Editor) => boolean;
  isEnabled?: boolean;
}
export interface BlockMenuItem {
  name: string;
  title: string;
  commands: CommandItem[];
}
export interface FeaturesSetting {
  textItalic: false;
  textUnderline: false;
  textStrike: false;
  textCode: false;
  textMarker: boolean;
  textColor: boolean;
  fontSize: boolean;
  fontFamily: boolean;
  textSubscript: boolean;
  textSuperscript: boolean;
  customClass: boolean;
  tableBgColor: boolean;
}

export interface BlockMenuProps extends React.CustomComponentPropsWithRef<typeof Menu> {
  editor: Editor;
  items: BlockMenuItem[];
  command: (command: CommandItem) => void;
}

export interface FontSizeItem {
  label: string;
  value: string;
}

export interface FontFamilyItem {
  label: string;
  value: string;
}

export interface CustomClassItem {
  label: string;
  value: string;
}

export interface ImageSizesItem {
  label: string;
  value: string;
}

export interface EditorSettings {
  features: FeaturesSetting;
  blockMenus: BlockMenuItem[];
  fontSize: FontSizeItem[];
  fontFamily: FontFamilyItem[];
  customClass: CustomClassItem[];
  imageSizes: ImageSizesItem[];
  colorPalette: string[];
}
