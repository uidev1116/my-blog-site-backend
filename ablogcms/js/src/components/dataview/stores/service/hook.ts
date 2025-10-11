import { useContext } from 'react';
import ColumnServiceContext from './context';

export default function useColumnService() {
  const context = useContext(ColumnServiceContext);
  if (!context) {
    throw new Error('useColumnService must be used within a ColumnServiceProvider');
  }
  return context;
}
