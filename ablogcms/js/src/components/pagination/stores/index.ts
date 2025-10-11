import { createContext, useContext } from 'react';

// Pagination コンテキストの型定義
interface PaginationContextProps {
  page: number;
  total: number;
  totalItems: number;
  pages: number[];
  pageSize: number;
  withNumbers: boolean;
  onChange: (page: number, event: React.MouseEvent) => void;
}

const PaginationContext = createContext<PaginationContextProps | undefined>(undefined);

// usePaginationContext フックを定義してコンテキストの値を取得
const usePaginationContext = () => {
  const context = useContext(PaginationContext);
  if (!context) {
    throw new Error('usePaginationContext must be used within PaginationRoot');
  }
  return context;
};

export { PaginationContext, usePaginationContext };
