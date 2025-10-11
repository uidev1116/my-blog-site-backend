import React, { useMemo } from 'react';
import ColumnServiceContext from './context';
import type { ColumnServiceInterface, RowData } from '../../types';

interface ColumnServiceProviderProps<T extends RowData> {
  service: ColumnServiceInterface<T>;
  children: React.ReactNode;
}

const ColumnServiceProvider = <T extends RowData>({ children, service }: ColumnServiceProviderProps<T>) => {
  const value = useMemo(() => service, [service]);
  return (
    <ColumnServiceContext.Provider value={value as ColumnServiceInterface<unknown>}>
      {children}
    </ColumnServiceContext.Provider>
  );
};

export default ColumnServiceProvider;
