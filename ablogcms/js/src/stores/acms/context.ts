import { createContext } from 'react';
import type { AcmsContext as AcmsContextType } from '../../lib/acmsPath/types';

interface AcmsContextProps {
  context: AcmsContextType;
}

const AcmsContext = createContext<AcmsContextProps>({
  context: {},
});

export default AcmsContext;
