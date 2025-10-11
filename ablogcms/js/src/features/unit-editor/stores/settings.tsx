import { createContext, useContext, ReactNode, useMemo } from 'react';
import { UnitEditorSettings } from '../types';

const SettingsContext = createContext<UnitEditorSettings | undefined>(undefined);

interface SettingsProviderProps {
  settings: UnitEditorSettings;
  children: ReactNode;
}

export const SettingsProvider = ({ children, settings }: SettingsProviderProps) => {
  const value = useMemo(() => settings, [settings]);
  return <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>;
};

export const useSettings = () => {
  const context = useContext(SettingsContext);
  if (context === undefined) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return context;
};
