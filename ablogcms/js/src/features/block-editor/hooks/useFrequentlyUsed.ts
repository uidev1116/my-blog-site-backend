import { useState } from 'react';
import type { CommandItem } from '@features/block-editor/types';
import {
  type CommandIdentifier,
  getFrequentlyUsedCommands,
  recordCommandUsage,
} from '@features/block-editor/lib/utils/commandUsageStorage';

const maxItem = 5; // 最大件数

export const useFrequentlyUsed = () => {
  const [frequentlyUsed, setFrequentlyUsed] = useState<CommandIdentifier[]>(() => getFrequentlyUsedCommands(maxItem));

  const recordUsage = (command: CommandItem): void => {
    recordCommandUsage(command);
    // 頻度順で再取得（カウント更新後の正しい順序を反映）
    setFrequentlyUsed(getFrequentlyUsedCommands(maxItem));
  };

  return { frequentlyUsed, recordUsage };
};
