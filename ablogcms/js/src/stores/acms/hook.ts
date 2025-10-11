import { useContext } from 'react';
import AcmsContext from './context';

export default function useAcmsContext() {
  return useContext(AcmsContext);
}
