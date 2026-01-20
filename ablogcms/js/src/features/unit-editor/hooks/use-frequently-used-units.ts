import { useState, useCallback, useMemo } from 'react';
import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';

// --- 定数と型定義 --- //
const STORAGE_KEY_PREFIX = 'acms-frequently-used-unit';

interface UsageRecord {
  count: number;
  lastUsed: number;
  score?: number;
}

type UsageData = Record<string, UsageRecord>;

export const FREQUENT_UNITS_MAX_ITEMS = 30; // localStorageに保存する最大件数
export const MAX_DISPLAY_ITEMS = 5; // 表示する最大件数
const FREQUENCY_WEIGHT = 0.3;
const RECENCY_WEIGHT = 0.5;
const RECENCY_HALFLIFE_DAYS = 7; // 直近使用の半減期（日数）

export type UnitIdentifier = Pick<UnitMenuItem, 'id'>; // id を持つオブジェクト

/**
 * localStorageのキーを生成する
 */
export const getKey = (): string => {
  return `${STORAGE_KEY_PREFIX}-setid-${ACMS.Config.editorSetId}-root-${ACMS.Config.root}-suid-${ACMS.Config.suid}`;
};

/**
 * localStorageからUsageDataを読み込む
 */
export const loadUsageData = (): UsageData => {
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
export const saveUsageData = (data: UsageData): void => {
  const key = getKey();
  localStorage.setItem(key, JSON.stringify(data));
};

/**
 * 個々のUsageRecordに対してスコアを計算する
 */
export const calculateScore = (record: UsageRecord, now: number): number => {
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
export const getSortedUnitEntries = (data: UsageData, limit: number, now: number): Array<[string, UsageRecord]> => {
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
 * ユニットの使用を記録する（純粋なロジック関数）
 */
export const recordUnitUsage = (unit: UnitIdentifier) => {
  const data = loadUsageData();
  const unitKey = unit.id;
  const now = Date.now();

  const existing = data[unitKey];
  data[unitKey] = {
    count: (existing?.count ?? 0) + 1,
    lastUsed: now,
  };

  // 保存件数をFREQUENT_UNITS_MAX_ITEMSに制限
  const trimmedEntries = Object.entries(data)
    .toSorted((a, b) => (b[1].lastUsed ?? 0) - (a[1].lastUsed ?? 0)) // 最新使用順でソート
    .slice(0, FREQUENT_UNITS_MAX_ITEMS);
  const trimmedData: UsageData = Object.fromEntries(trimmedEntries);

  saveUsageData(trimmedData);
};

/**
 * よく使うユニットのIDリストを取得する（純粋なロジック関数）
 */
export const getFrequentlyUsedUnits = (limit = MAX_DISPLAY_ITEMS): string[] => {
  const data = loadUsageData();
  const now = Date.now();

  const sortedEntries = getSortedUnitEntries(data, FREQUENT_UNITS_MAX_ITEMS, now); // まずFREQUENT_UNITS_MAX_ITEMS件でソート＆トリミング

  // 必要な件数にスライスしてIDのみを返す
  return sortedEntries.slice(0, limit).map(([unitKey]) => unitKey);
};

export const useFrequentlyUsedUnits = () => {
  // 内部的な使用履歴データ
  const [usageData, setUsageData] = useState<UsageData>(() => loadUsageData());

  // 表示用のソートされたユニットIDリスト
  const frequentlyUsed = useMemo<string[]>(() => {
    const now = Date.now();
    // MAX_DISPLAY_ITEMS で表示件数を制限
    const sortedEntries = getSortedUnitEntries(usageData, MAX_DISPLAY_ITEMS, now);
    return sortedEntries.map(([unitKey]) => unitKey);
  }, [usageData]);

  const recordUsage = useCallback((unitId: string): void => {
    // 純粋なロジック関数を呼び出す
    recordUnitUsage({ id: unitId });
    // localStorageが更新されたので、状態を再ロード
    setUsageData(loadUsageData());
  }, []);

  return { frequentlyUsed, recordUsage };
};
