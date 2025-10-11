import { useMemo } from 'react';
import AcmsContext from './context';
import { AcmsContext as AcmsContextType } from '../../lib/acmsPath/types';

interface AcmsContextProviderProps {
  context: AcmsContextType;
  children: React.ReactNode;
}

const AcmsContextProvider = ({ context, children }: AcmsContextProviderProps) => {
  const value = useMemo(() => ({ context }), [context]);
  return <AcmsContext.Provider value={value}>{children}</AcmsContext.Provider>;
};

export default AcmsContextProvider;
