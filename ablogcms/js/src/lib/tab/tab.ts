import { getNextActiveElement, getSelector, isDisabled } from '../../utils/dom';
import { hide, show } from '../dom';

const ARROW_LEFT_KEY = 'ArrowLeft';
const ARROW_RIGHT_KEY = 'ArrowRight';
const ARROW_UP_KEY = 'ArrowUp';
const ARROW_DOWN_KEY = 'ArrowDown';
const HOME_KEY = 'Home';
const END_KEY = 'End';

const SELECTOR_OUTER = 'li';
export const SELECTOR_TAB = 'a[href^="#"], [role="tab"]';

export interface TabOptions {
  tabList: string | HTMLElement;
  tabClass: string;
  activeClass: string;
  readyMark: string;
  enableHashNavigation: boolean;
  onShow: ({ tab, panel }: { tab: HTMLElement; panel: HTMLElement | null }) => void;
  onHide: ({ tab, panel }: { tab: HTMLElement; panel: HTMLElement | null }) => void;
}

export const defaultOptions: TabOptions = {
  tabList: '.js-acms_admin_tabs',
  tabClass: 'js-acms_tab',
  activeClass: 'js-acms_tab-active',
  readyMark: '.js-ready-acms_tabs',
  enableHashNavigation: false,
  onShow: () => {},
  onHide: () => {},
};

type TabElement = HTMLElement & { Tab?: Tab };

export default class Tab {
  private _element: TabElement;

  private _tabList: HTMLElement;

  private _options: TabOptions;

  private eventRegistry: Map<
    Element | Document,
    Array<{
      type: string;
      listener: EventListener;
      options?: AddEventListenerOptions;
    }>
  > = new Map();

  constructor(element: TabElement, options: Partial<TabOptions> = {}) {
    this._options = { ...defaultOptions, ...options };
    this._element = element;
    const tabList =
      this._options.tabList instanceof HTMLElement
        ? this._options.tabList
        : this._element.closest<HTMLElement>(this._options.tabList);

    if (!tabList) {
      throw new TypeError(`${element.outerHTML} has not a valid parent ${this._options.tabList}`);
    }

    this._tabList = tabList;

    const tabs = this._getTabs();
    // Set up initial aria attributes
    this._setInitialAttributes(this._tabList, tabs);
    this._keydown = this._keydown.bind(this);
    this._click = this._click.bind(this);
    this._element.addEventListener('click', this._click);
    this._element.addEventListener('keydown', this._keydown);
    this.eventRegistry.set(this._element, [
      {
        type: 'click',
        listener: this._click as EventListener,
      },
      {
        type: 'keydown',
        listener: this._keydown as EventListener,
      },
    ]);
  }

  public destroy() {
    delete this._element.Tab;
    for (const [eventTarget, listeners] of this.eventRegistry) {
      listeners.forEach(({ type, listener, options }) => {
        eventTarget.removeEventListener(type, listener, options);
      });
    }
    this.eventRegistry.clear();
  }

  // Public
  public show() {
    // Shows this elem and deactivate the active sibling if exists
    const tab = this._element;
    if (this._tabIsActive(tab)) {
      return;
    }

    // Search for active tab on same parent to deactivate it
    const activeTab = this._getActiveTab();

    if (activeTab) {
      this._deactivate(activeTab);
    }

    this._activate(tab);
    const panel = this._getTargetPanel(tab);
    if (this._enableHashNavigation() && panel && `#${panel.id}` !== location.hash) {
      const hash = panel.id;
      if ('history' in window) {
        history.replaceState(null, '', `#${hash}`);
      }
      panel.id = hash;
    }
  }

  public get options(): TabOptions {
    return this._options;
  }

  // Private
  private _activate(tab: HTMLElement) {
    const panel = this._getTargetPanel(tab);

    tab.classList.add(this._options.activeClass);

    if (panel !== null) {
      show(panel);
      panel.setAttribute('aria-hidden', 'false');
    }

    tab.removeAttribute('tabindex');
    tab.setAttribute('aria-selected', 'true');

    this._options.onShow({ tab, panel });
  }

  private _deactivate(tab: HTMLElement) {
    const panel = this._getTargetPanel(tab);

    tab.classList.remove(this._options.activeClass);
    tab.blur();

    if (panel !== null) {
      hide(panel);
      panel.setAttribute('aria-hidden', 'true');
    }

    tab.setAttribute('aria-selected', 'false');
    tab.setAttribute('tabindex', '-1');

    this._options.onHide({ tab, panel });
  }

  private _click(event: MouseEvent) {
    const { currentTarget } = event;
    if (!(currentTarget instanceof HTMLElement)) {
      return;
    }

    if (['A', 'AREA'].includes(currentTarget.tagName)) {
      event.preventDefault();
    }

    if (isDisabled(currentTarget)) {
      return;
    }

    Tab.getOrCreateInstance(currentTarget).show();
  }

  private _keydown(event: KeyboardEvent) {
    if (![ARROW_LEFT_KEY, ARROW_RIGHT_KEY, ARROW_UP_KEY, ARROW_DOWN_KEY, HOME_KEY, END_KEY].includes(event.key)) {
      return;
    }

    if (!(event.target instanceof HTMLElement)) {
      return;
    }

    event.stopPropagation(); // stopPropagation/preventDefault both added to support up/down keys without scrolling the page
    event.preventDefault();

    const activeTabs = this._getTabs().filter((tab) => !isDisabled(tab));
    let nextActiveElement;

    if ([HOME_KEY, END_KEY].includes(event.key)) {
      nextActiveElement = activeTabs[event.key === HOME_KEY ? 0 : activeTabs.length - 1];
    } else {
      const isNext = [ARROW_RIGHT_KEY, ARROW_DOWN_KEY].includes(event.key);
      nextActiveElement = getNextActiveElement(activeTabs, event.target, isNext, true);
    }

    if (nextActiveElement) {
      nextActiveElement.focus({ preventScroll: true });
      Tab.getOrCreateInstance(nextActiveElement).show();
    }
  }

  private _getTabs() {
    return Array.from(this._tabList.querySelectorAll<HTMLElement>(SELECTOR_TAB));
  }

  private _getActiveTab() {
    return this._getTabs().find((tab) => this._tabIsActive(tab)) || null;
  }

  private _getReadyTab() {
    return this._getTabs().find((tab) => tab.matches(this._options.readyMark)) || null;
  }

  private _getTabFromHash() {
    return this._getTabs().find((tab) => getSelector(tab) === location.hash) || null;
  }

  private _getInitialActiveTab() {
    if (this._enableHashNavigation()) {
      const hashTab = this._getTabFromHash();
      if (hashTab) {
        return hashTab;
      }
    }
    const readyTab = this._getReadyTab();
    if (readyTab) {
      return readyTab;
    }
    return this._getTabs().at(0);
  }

  private _setInitialAttributes(parent: HTMLElement, tabs: HTMLElement[]) {
    this._setAttributeIfNotExists(parent, 'role', 'tablist');

    const initialActiveTab = this._getInitialActiveTab();
    if (initialActiveTab) {
      this._activate(initialActiveTab);
    }

    for (const tab of tabs) {
      this._setInitialAttributesOnTab(tab);
    }
  }

  private _setInitialAttributesOnTab(tab: HTMLElement) {
    const isActive = this._tabIsActive(tab);
    const outerElem = this._getOuterElement(tab);
    const panel = this._getTargetPanel(tab);

    tab.classList.add(this._options.tabClass);
    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');

    if (outerElem !== tab) {
      this._setAttributeIfNotExists(outerElem, 'role', 'presentation');
    }

    if (!isActive) {
      tab.setAttribute('tabindex', '-1');
    }

    this._setAttributeIfNotExists(tab, 'role', 'tab');

    if (panel) {
      this._setAttributeIfNotExists(tab, 'aria-controls', panel.id);
      this._setAttributeIfNotExists(tab, 'id', `${panel.id}-tab`);
    }

    // set attributes to the related panel too
    this._setInitialAttributesOnTargetPanel(tab);
  }

  private _setInitialAttributesOnTargetPanel(tab: HTMLElement) {
    const panel = this._getTargetPanel(tab);

    if (!panel) {
      return;
    }

    const isActive = this._tabIsActive(tab);
    this._setAttributeIfNotExists(panel, 'role', 'tabpanel');
    this._setAttributeIfNotExists(panel, 'aria-hidden', isActive ? 'false' : 'true');
    if (!isActive) {
      panel.style.display = 'none';
    }

    if (tab.id) {
      this._setAttributeIfNotExists(panel, 'aria-labelledby', `${tab.id}`);
    }
  }

  private _getTargetPanel(tab: HTMLElement): HTMLElement | null {
    const selector = getSelector(tab);
    if (!selector) {
      return null;
    }
    return document.querySelector(selector);
  }

  private _setAttributeIfNotExists(element: HTMLElement, attribute: string, value: string) {
    if (!element.hasAttribute(attribute)) {
      element.setAttribute(attribute, value);
    }
  }

  private _tabIsActive(tab: HTMLElement) {
    return tab.classList.contains(this._options.activeClass);
  }

  // Try to get the outer element (usually the .nav-item)
  private _getOuterElement(elem: HTMLElement) {
    return elem.closest(SELECTOR_OUTER) || elem;
  }

  /**
   * ハッシュナビゲーションを有効にするかどうかを判断する
   */
  private _enableHashNavigation() {
    if (this._options.enableHashNavigation) {
      return true;
    }

    if (this._options.readyMark.startsWith('#')) {
      // readyMarkにハッシュが含まれている場合はハッシュナビゲーションを有効にする
      return true;
    }
    return false;
  }

  static getOrCreateInstance(element: HTMLElement & { Tab?: Tab }, options: Partial<TabOptions> = {}): Tab {
    if (element.Tab instanceof Tab) {
      return element.Tab;
    }
    const tab = new Tab(element, options);
    element.Tab = tab;
    return tab;
  }
}
