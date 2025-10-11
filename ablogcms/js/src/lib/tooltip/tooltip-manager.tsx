import { Tooltip } from '@components/tooltip';
import useMutationObserver from '@hooks/use-mutation-observer';
import useEffectOnce from '@hooks/use-effect-once';
import { fixTooltipAttribute } from './compatibility';
import { setA11yAttributesToTooltipTrigger } from './a11y';

const selector = '.js-acms-tooltip, .js-acms-tooltip-hover';
const tooltipId = 'acms-tooltip';

const TooltipManager = () => {
  useEffectOnce(() => {
    // 初期要素に変換適用
    const elements = document.querySelectorAll<HTMLElement>(selector);
    elements.forEach((element) => fixTooltipAttribute(element, tooltipId));
    elements.forEach((element) => setA11yAttributesToTooltipTrigger(element, tooltipId));
  });

  useMutationObserver(
    (mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (!(node instanceof HTMLElement)) continue;
          if (node.matches(selector)) {
            fixTooltipAttribute(node, tooltipId);
            setA11yAttributesToTooltipTrigger(node, tooltipId);
          }
          const elements = node.querySelectorAll<HTMLElement>(selector);
          elements.forEach((element) => fixTooltipAttribute(element, tooltipId));
          elements.forEach((element) => setA11yAttributesToTooltipTrigger(element, tooltipId));
        }
      }
    },
    { childList: true, subtree: true },
    document.body
  );

  return <Tooltip id={tooltipId} />;
};

export default TooltipManager;
