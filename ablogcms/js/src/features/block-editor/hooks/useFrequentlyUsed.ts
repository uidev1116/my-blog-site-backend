import { useState } from 'react';
import { getFrequentlyUsedCommands, recordCommandUsage } from '@features/block-editor/lib/utils/commandUsageStorage';

const maxItem = 5; // 最大件数

export const useFrequentlyUsed = () => {
  const [frequentlyUsed, setFrequentlyUsed] = useState<string[]>(() => getFrequentlyUsedCommands(maxItem));
  const recordUsage = (command: string): void => {
    recordCommandUsage(command);
    setFrequentlyUsed((prev) => {
      const newList = [command, ...prev.filter((c) => c !== command)];
      return newList.slice(0, maxItem);
    });
  };

  return { frequentlyUsed, recordUsage };
};
