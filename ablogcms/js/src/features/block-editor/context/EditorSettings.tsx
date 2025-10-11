import React, { createContext, useContext } from 'react';
import type { EditorSettings } from '@features/block-editor/types';

const SettingsContext = createContext<EditorSettings>({
  features: {
    textItalic: false,
    textUnderline: false,
    textStrike: false,
    textCode: false,
    textMarker: false,
    textColor: false,
    fontSize: false,
    fontFamily: false,
    textSubscript: false,
    textSuperscript: false,
    customClass: false,
    tableBgColor: false,
  },
  blockMenus: [],
  fontSize: [],
  fontFamily: [],
  customClass: [],
  imageSizes: [],
  colorPalette: [],
});

export const SettingsProvider = ({ children, settings }: { children: React.ReactNode; settings: EditorSettings }) => {
  return <SettingsContext.Provider value={settings}>{children}</SettingsContext.Provider>;
};

export const useSettingsContext = (): EditorSettings => {
  return useContext(SettingsContext);
};
