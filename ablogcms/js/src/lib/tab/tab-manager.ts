import Tab, { SELECTOR_TAB, TabOptions } from './tab';

export default class TabManager {
  private static instance: TabManager | null = null;

  private tabRepository: Map<TabOptions['tabList'], HTMLElement[]> = new Map();

  static getInstance(): TabManager {
    if (!TabManager.instance) {
      TabManager.instance = new TabManager();
    }
    return TabManager.instance;
  }

  async apply(
    selector: Extract<TabOptions['tabList'], string>,
    options: Partial<Omit<TabOptions, 'tabListSelector'>> = {},
    context: Document | HTMLElement = document
  ): Promise<void> {
    const tabOptions: Partial<TabOptions> = {
      tabList: selector,
      ...options,
    };

    ACMS.Loaded(() => {
      context.querySelectorAll<HTMLElement>(SELECTOR_TAB).forEach((element) => {
        if (element.closest(selector) !== null) {
          Tab.getOrCreateInstance(element, tabOptions);
          this.tabRepository.set(selector, [...(this.tabRepository.get(selector) || []), element]);
        }
      });
    });
  }

  destroy(selector: Extract<TabOptions['tabList'], string>): void {
    const elements = this.tabRepository.get(selector);
    if (elements) {
      elements.forEach((element) => {
        Tab.getOrCreateInstance(element).destroy();
      });
      this.tabRepository.delete(selector);
    }
  }

  destroyAll(): void {
    for (const [, elements] of this.tabRepository) {
      elements.forEach((element) => {
        Tab.getOrCreateInstance(element).destroy();
      });
    }
    this.tabRepository.clear();
  }
}
