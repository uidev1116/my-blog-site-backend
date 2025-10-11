import { createContext, useContext } from 'react';
import type { PopoverContextType } from './types';

// PopoverContextの初期値はnull
export const PopoverContext = createContext<PopoverContextType | null>(null);

// Popover内のコンポーネントからコンテキストを取得するためのフック
export function usePopoverContext(): PopoverContextType {
  const context = useContext(PopoverContext);

  if (!context) {
    throw new Error('Popover components must be used within a <Popover />');
  }

  return context;
}
