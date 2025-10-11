import { createContext, useContext, useMemo } from 'react';
import UnitToolbar from './unit-toolbar';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface UnitToolbarContextType extends React.ComponentPropsWithoutRef<typeof UnitToolbar> {}

const UnitToolbarContext = createContext<UnitToolbarContextType | undefined>(undefined);

interface UnitToolbarProviderProps extends UnitToolbarContextType {
  children: React.ReactNode;
}

export const UnitToolbarProvider = ({ children, ...props }: UnitToolbarProviderProps) => {
  const value = useMemo(() => ({ ...props }), [props]);
  return <UnitToolbarContext.Provider value={value}>{children}</UnitToolbarContext.Provider>;
};

export function useUnitToolbarProps() {
  const context = useContext(UnitToolbarContext);
  if (!context) {
    throw new Error('useUnitToolbarContext must be used within a ToolbarProvider');
  }
  return context;
}
