import { useCallback, useRef } from 'react';
import type { TabOptions } from '../../../lib/tab/tab';
import TabManager from '../../../lib/tab/tab-manager';

const selector = '.js-acms_admin_tabs';

const tabOptions: Partial<TabOptions> = {
  enableHashNavigation: false,
  onShow({ tab, panel }) {
    ACMS.dispatchEvent('acmsAdminShowTabPanel', tab, { tab, panel });
  },
  onHide({ tab, panel }) {
    ACMS.dispatchEvent('acmsAdminHideTabPanel', tab, { tab, panel });
  },
  tabList: selector,
};

export default function useTab() {
  const ref = useRef<HTMLElement>(null);
  const tabManagerRef = useRef<TabManager | null>(null);
  const apply = useCallback(async () => {
    if (ref.current) {
      const tabManager = TabManager.getInstance();
      tabManager.apply(selector, tabOptions, ref.current);
      tabManagerRef.current = tabManager;
    }
  }, []);

  const destroy = useCallback(() => {
    if (ref.current) {
      tabManagerRef.current?.destroyAll();
    }
  }, []);

  return { ref, apply, destroy };
}
