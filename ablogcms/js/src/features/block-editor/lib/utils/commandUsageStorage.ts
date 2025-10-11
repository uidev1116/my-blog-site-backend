const STORAGE_KEY = 'frequentlyUsedCommands';

const getKey = (): string => {
  return `${STORAGE_KEY}-${ACMS.Config.editorSetId}-${ACMS.Config.root}-${ACMS.Config.suid}`;
};

const recordCommandUsage = (commandName: string) => {
  const key = getKey();
  const raw = localStorage.getItem(key);
  let list: string[] = raw ? JSON.parse(raw) : [];
  list = list.filter((name) => name !== commandName); // 既存にあれば削除
  list.unshift(commandName); // 先頭に追加（最新が先頭）
  list = list.slice(0, 30);
  localStorage.setItem(key, JSON.stringify(list));
};

const getFrequentlyUsedCommands = (limit = 5): string[] => {
  const key = getKey();
  const raw = localStorage.getItem(key);
  if (!raw) return [];
  const list: string[] = JSON.parse(raw);
  return list.slice(0, limit);
};

export { recordCommandUsage, getFrequentlyUsedCommands };
