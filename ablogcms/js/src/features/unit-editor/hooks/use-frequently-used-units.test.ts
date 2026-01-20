import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';
import {
  recordUnitUsage,
  getFrequentlyUsedUnits,
  getKey,
  FREQUENT_UNITS_MAX_ITEMS,
  MAX_DISPLAY_ITEMS,
} from './use-frequently-used-units';

// @ts-expect-error ACMS.Config をモック
global.ACMS = {
  Config: {
    editorSetId: 'test-setid',
    root: 'test-root',
    suid: 'test-suid',
  },
};

describe('useFrequentlyUsedUnits (logic only)', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // UnitIdentifier のダミーデータ
  const mockUnit1: Pick<UnitMenuItem, 'id'> = { id: 'unit1' };
  const mockUnit2: Pick<UnitMenuItem, 'id'> = { id: 'unit2' };
  const mockUnit3: Pick<UnitMenuItem, 'id'> = { id: 'unit3' };

  it('recordUnitUsage: 新しいユニットを正しく記録する', () => {
    recordUnitUsage(mockUnit1);
    const data = JSON.parse(localStorage.getItem(getKey()) || '{}');
    expect(data.unit1).toEqual({
      count: 1,
      lastUsed: Date.now(),
    });
  });

  it('recordUnitUsage: 既存のユニットの使用回数と最終使用日時を更新する', () => {
    recordUnitUsage(mockUnit1);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordUnitUsage(mockUnit1);
    const secondRecordedTime = Date.now(); // 2回目の記録日時を取得

    const data = JSON.parse(localStorage.getItem(getKey()) || '{}');
    expect(data.unit1.count).toBe(2);
    expect(data.unit1.lastUsed).toBe(secondRecordedTime);
  });

  it('getFrequentlyUsedUnits: スコアに基づいてユニットをソートして返す', () => {
    const now = Date.now();
    vi.setSystemTime(now);

    // ユニット1: 頻度高 (3回), 直近
    recordUnitUsage(mockUnit1);
    recordUnitUsage(mockUnit1);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordUnitUsage(mockUnit1);
    vi.advanceTimersByTime(100); // さらに少し進める

    // ユニット2: 頻度中 (2回), 少し前
    recordUnitUsage(mockUnit2);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordUnitUsage(mockUnit2);
    vi.advanceTimersByTime(1000 * 60 * 60 * 24 * 3); // 3日進める

    // ユニット3: 頻度低 (1回), かなり前
    recordUnitUsage(mockUnit3);
    vi.advanceTimersByTime(1000 * 60 * 60 * 24 * 10); // 10日進める

    const frequentlyUsed = getFrequentlyUsedUnits(MAX_DISPLAY_ITEMS);
    // スコア計算により、mockUnit1 > mockUnit2 > mockUnit3 の順になるはず
    expect(frequentlyUsed[0]).toBe('unit1');
    expect(frequentlyUsed[1]).toBe('unit2');
    expect(frequentlyUsed[2]).toBe('unit3');
  });

  it('getFrequentlyUsedUnits: MAX_DISPLAY_ITEMS に制限される', () => {
    for (let i = 0; i < 10; i++) {
      // 10個のユニットを記録
      recordUnitUsage({ id: `unit${i}` });
      vi.advanceTimersByTime(100);
    }
    // 表示はMAX_DISPLAY_ITEMS (5) 件に制限されるはず
    const frequentlyUsed = getFrequentlyUsedUnits(MAX_DISPLAY_ITEMS);
    expect(frequentlyUsed.length).toBe(MAX_DISPLAY_ITEMS);
  });

  it('getFrequentlyUsedUnits: 空のデータの場合に空の配列を返す', () => {
    const frequentlyUsed = getFrequentlyUsedUnits(MAX_DISPLAY_ITEMS);
    expect(frequentlyUsed).toEqual([]);
  });

  it('recordUnitUsage: FREQUENT_UNITS_MAX_ITEMS を超えた場合に localStorage のエントリ数が制限されること', () => {
    for (let i = 0; i < FREQUENT_UNITS_MAX_ITEMS + 5; i++) {
      // FREQUENT_UNITS_MAX_ITEMS + 5 個のユニークなユニットを記録
      recordUnitUsage({ id: `unit${i}` });
      vi.advanceTimersByTime(100);
    }

    const data = JSON.parse(localStorage.getItem(getKey()) || '{}');
    expect(Object.keys(data).length).toBe(FREQUENT_UNITS_MAX_ITEMS);
  });
});
