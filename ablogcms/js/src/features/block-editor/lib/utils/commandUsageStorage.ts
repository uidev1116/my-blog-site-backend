import type { CommandItem } from '@features/block-editor/types';

const STORAGE_KEY_PREFIX = 'acms-frequently-used-commands';

export type CommandIdentifier = Pick<CommandItem, 'name' | 'class'>;

interface UsageRecord {
  count: number;
  lastUsed: number;
  score?: number;
}

type UsageData = Record<string, UsageRecord>;

const FREQUENT_BLOCKS_MAX_ITEMS = 30; // localStorageに保存する最大件数
const FREQUENCY_WEIGHT = 0.3;
const RECENCY_WEIGHT = 0.5;
const RECENCY_HALFLIFE_DAYS = 7; // 直近使用の半減期（日数）

/**
 * localStorageのキーを生成する
 */
export const getKey = (): string => {
  return `${STORAGE_KEY_PREFIX}-setid-${ACMS.Config.editorSetId}-root-${ACMS.Config.root}-suid-${ACMS.Config.suid}`;
};

/**
 * コマンドを一意に識別するキーを生成
 */
const getCommandKey = (command: CommandIdentifier): string => {
  return command.class ? `${command.name}:${command.class}` : command.name;
};

/**
 * localStorageからUsageDataを読み込む
 */
const loadUsageData = (): UsageData => {
  const key = getKey();
  const raw = localStorage.getItem(key);
  if (!raw) return {};
  try {
    const parsed: unknown = JSON.parse(raw);
    // 簡易的な型チェック
    if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
      return parsed as UsageData;
    }
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('Error parsing usage data from localStorage:', e);
  }
  return {};
};

/**
 * UsageDataをlocalStorageに保存する
 */
const saveUsageData = (data: UsageData): void => {
  const key = getKey();
  localStorage.setItem(key, JSON.stringify(data));
};

/**
 * コマンドの使用を記録する
 */
export const recordCommandUsage = (command: CommandItem) => {
  const data = loadUsageData();
  const commandKey = getCommandKey({ name: command.name, class: command.class });
  const now = Date.now();

  const existing = data[commandKey];
  data[commandKey] = {
    count: (existing?.count ?? 0) + 1,
    lastUsed: now,
  };

  // 保存件数をMAX_STOREDに制限（ソートは取得時に行う）
  const trimmedEntries = Object.entries(data)
    .toSorted((a, b) => (b[1].lastUsed ?? 0) - (a[1].lastUsed ?? 0)) // 最新使用順でソート
    .slice(0, FREQUENT_BLOCKS_MAX_ITEMS);
  const trimmedData: UsageData = Object.fromEntries(trimmedEntries);

  saveUsageData(trimmedData);
};

/**
 * 個々のUsageRecordに対してスコアを計算する
 */
const calculateScore = (record: UsageRecord, now: number): number => {
  const useCount = record.count;
  const { lastUsed } = record;

  // ① 使用回数（Frequency）スコア
  const frequencyScore = Math.log(useCount + 1);

  // ② 直近使用（Recency）スコア
  const daysSinceLastUse = (now - lastUsed) / (1000 * 60 * 60 * 24);
  const recencyScore = Math.exp(-daysSinceLastUse / RECENCY_HALFLIFE_DAYS); // 半減期

  // 最終スコア
  return frequencyScore * FREQUENCY_WEIGHT + recencyScore * RECENCY_WEIGHT;
};

/**
 * UsageDataをスコアに基づいてソートし、指定された件数にトリミングする
 */
const getSortedCommandEntries = (data: UsageData, limit: number, now: number): Array<[string, UsageRecord]> => {
  const entriesWithScore = Object.entries(data).map(([id, record]) => {
    const score = calculateScore(record, now);
    return [id, { ...record, score }] as [string, UsageRecord];
  });

  // スコアでソート
  return entriesWithScore
    .toSorted((a, b) => {
      const scoreA = a[1].score ?? 0;
      const scoreB = b[1].score ?? 0;
      return scoreB - scoreA; // スコアが高い順
    })
    .slice(0, limit);
};

/**
 * よく使うコマンドのリストを取得する（頻度順、同率なら最新使用順）
 */
export const getFrequentlyUsedCommands = (limit = 5): CommandIdentifier[] => {
  const data = loadUsageData();
  const now = Date.now();

  const sortedEntries = getSortedCommandEntries(data, FREQUENT_BLOCKS_MAX_ITEMS, now); // まずFREQUENT_BLOCKS_MAX_ITEMS件でソート＆トリミング

  // 必要な件数にスライスしてCommandIdentifierに変換
  return sortedEntries.slice(0, limit).map(([commandKey]) => {
    // commandKey を name と class に分解
    const colonIndex = commandKey.indexOf(':');
    if (colonIndex !== -1) {
      return {
        name: commandKey.substring(0, colonIndex),
        class: commandKey.substring(colonIndex + 1),
      };
    }
    return { name: commandKey };
  });
};
