import { createContext } from 'react';
import { ColumnServiceInterface } from '../../types';

const ColumnServiceContext = createContext<ColumnServiceInterface<unknown> | undefined>(undefined);

export default ColumnServiceContext;
