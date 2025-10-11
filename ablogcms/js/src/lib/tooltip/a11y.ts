export const setA11yAttributesToTooltipTrigger = (element: HTMLElement, tooltipId: string) => {
  element.setAttribute('role', 'button');
  element.setAttribute('tabindex', '0');
  element.setAttribute('aria-label', ACMS.i18n('tooltip.trigger.label'));
  element.setAttribute('aria-describedby', tooltipId);
};
