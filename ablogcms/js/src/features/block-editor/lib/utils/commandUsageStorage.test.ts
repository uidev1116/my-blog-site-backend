import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import type { CommandItem } from '@features/block-editor/types';
import { recordCommandUsage, getFrequentlyUsedCommands, getKey } from './commandUsageStorage';

// @ts-expect-error ACMS.Config をモック
global.ACMS = {
  Config: {
    editorSetId: 'test-setid',
    root: 'test-root',
    suid: 'test-suid',
  },
};

describe('commandUsageStorage', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  const mockCommand1: CommandItem = {
    name: 'command1',
    class: 'class1',
    label: 'Command 1',
    description: 'Description 1',
    iconName: 'home',
    isTextMenu: false,
    isDisabled: () => false,
  };
  const mockCommand2: CommandItem = {
    name: 'command2',
    class: 'class2',
    label: 'Command 2',
    description: 'Description 2',
    iconName: 'settings',
    isTextMenu: false,
    isDisabled: () => false,
  };
  const mockCommand3: CommandItem = {
    name: 'command3',
    label: 'Command 3',
    description: 'Description 3',
    iconName: 'add',
    isTextMenu: false,
    isDisabled: () => false,
  };

  it('recordCommandUsage: 新しいコマンドを正しく記録する', () => {
    recordCommandUsage(mockCommand1);
    const data = JSON.parse(localStorage.getItem(getKey()) || '{}');
    expect(data['command1:class1']).toEqual({
      count: 1,
      lastUsed: vi.fn().mockImplementation(() => Date.now())(),
    });
  });

  it('recordCommandUsage: 既存のコマンドの使用回数と最終使用日時を更新する', () => {
    recordCommandUsage(mockCommand1);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordCommandUsage(mockCommand1);
    const secondRecordedTime = Date.now(); // 2回目の記録日時を取得

    const data = JSON.parse(localStorage.getItem(getKey()) || '{}');
    expect(data['command1:class1'].count).toBe(2);
    expect(data['command1:class1'].lastUsed).toBe(secondRecordedTime); // 2回目の記録日時と比較
  });

  it('getFrequentlyUsedCommands: スコアに基づいてコマンドをソートして返す', () => {
    const now = Date.now();
    vi.setSystemTime(now);

    // コマンド1: 頻度高、直近
    recordCommandUsage(mockCommand1);
    recordCommandUsage(mockCommand1);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordCommandUsage(mockCommand1);
    vi.advanceTimersByTime(100); // さらに少し進める

    // コマンド2: 頻度中 (2回), 少し前
    recordCommandUsage(mockCommand2);
    vi.advanceTimersByTime(1000); // 1秒進める
    recordCommandUsage(mockCommand2);
    vi.advanceTimersByTime(1000 * 60 * 60 * 24 * 3); // 3日進める

    // コマンド3: 頻度低 (1回), かなり前
    recordCommandUsage(mockCommand3);
    vi.advanceTimersByTime(1000 * 60 * 60 * 24 * 10); // 10日進める

    const frequentlyUsed = getFrequentlyUsedCommands(5);
    // スコア計算により、mockCommand1 > mockCommand2 > mockCommand3 の順になるはず
    expect(frequentlyUsed[0]).toEqual({ name: 'command1', class: 'class1' });
    expect(frequentlyUsed[1]).toEqual({ name: 'command2', class: 'class2' });
    expect(frequentlyUsed[2]).toEqual({ name: 'command3' });
  });

  it('getFrequentlyUsedCommands: limit引数が正しく機能する', () => {
    recordCommandUsage(mockCommand1);
    recordCommandUsage(mockCommand2);
    recordCommandUsage(mockCommand3);
    const frequentlyUsed = getFrequentlyUsedCommands(2);
    expect(frequentlyUsed.length).toBe(2);
  });

  it('getFrequentlyUsedCommands: 空のデータの場合に空の配列を返す', () => {
    const frequentlyUsed = getFrequentlyUsedCommands(5);
    expect(frequentlyUsed).toEqual([]);
  });

  it('getFrequentlyUsedCommands: コマンドキーが正しくCommandIdentifierに分解されること', () => {
    const testCommandWithClass: CommandItem = {
      name: 'testname',
      class: 'testclass',
      label: 'Test Name',
      description: 'Test Description',
      iconName: 'build',
      isTextMenu: false,
      isDisabled: () => false,
    };
    recordCommandUsage(testCommandWithClass);

    const testCommandOnlyName: CommandItem = {
      name: 'onlyname',
      label: 'Only Name',
      description: 'Only Name Description',
      iconName: 'visibility',
      isTextMenu: false,
      isDisabled: () => false,
    };
    recordCommandUsage(testCommandOnlyName);

    const frequentlyUsed = getFrequentlyUsedCommands(5);

    const cmdWithClass = frequentlyUsed.find((cmd) => cmd.name === 'testname');
    expect(cmdWithClass).toEqual({ name: 'testname', class: 'testclass' });

    const cmdOnlyName = frequentlyUsed.find((cmd) => cmd.name === 'onlyname');
    expect(cmdOnlyName).toEqual({ name: 'onlyname' });
  });
});
