import type TabManager from '../../lib/tab/tab-manager';

// シングルトンのTabManagerインスタンス
let tabManager: TabManager | null = null;

// tab関数でTabManagerをラップ
const tab = async (...args: Parameters<TabManager['apply']>) => {
  if (!tabManager) {
    const { default: TabManager } = await import(
      /* webpackChunkName: "acms-tab-manager" */ '../../lib/tab/tab-manager'
    );
    tabManager = TabManager.getInstance();
  }
  tabManager.apply(...args);
  return tabManager;
};

export default tab;
