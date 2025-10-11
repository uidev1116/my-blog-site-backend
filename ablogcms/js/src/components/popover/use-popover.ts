import * as React from 'react';
import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  useClick,
  useDismiss,
  useRole,
  useInteractions,
  Placement,
  OffsetOptions,
  FlipOptions,
  ShiftOptions,
  VirtualElement,
  UseFloatingOptions,
  UseClickProps,
  FloatingFocusManagerProps,
  UseDismissProps,
  UseRoleProps,
} from '@floating-ui/react';

export interface PopoverOptions {
  defaultIsOpen?: boolean;
  placement?: Placement;
  modal?: boolean;
  isOpen?: boolean;
  offsetOptions?: OffsetOptions;
  flipOptions?: FlipOptions;
  shiftOptions?: ShiftOptions;
  onOpenChange?: (open: boolean) => void;
  getReferenceElement?: () => Element | null;
  getAnchorRect?: () => DOMRect | null;
  floatingOptions?: Partial<UseFloatingOptions>;
  clickOptions?: Partial<UseClickProps>;
  roleOptions?: Partial<UseRoleProps>;
  dismissOptions?: Partial<UseDismissProps>;
  focusManagerOptions?: Partial<FloatingFocusManagerProps>;
}

export default function usePopover({
  defaultIsOpen = false,
  placement = 'bottom',
  modal,
  isOpen: controlledIsOpen,
  offsetOptions = 5,
  flipOptions = {
    crossAxis: placement.includes('-'),
    fallbackAxisSideDirection: 'end',
    padding: 5,
  },
  shiftOptions = { padding: 5 },
  onOpenChange: setControlledIsOpen,
  getReferenceElement,
  getAnchorRect,
  floatingOptions = {},
  clickOptions = {},
  roleOptions = {},
  dismissOptions = {},
  focusManagerOptions = {},
}: PopoverOptions = {}) {
  const [uncontrolledIsOpen, setUncontrolledIsOpen] = React.useState(defaultIsOpen);

  const isOpen = controlledIsOpen ?? uncontrolledIsOpen;
  const setIsOpen = setControlledIsOpen ?? setUncontrolledIsOpen;

  const openPopover = React.useCallback(() => {
    setIsOpen(true);
  }, [setIsOpen]);

  const closePopover = React.useCallback(() => {
    setIsOpen(false);
  }, [setIsOpen]);

  const togglePopover = React.useCallback(() => {
    if (isOpen) {
      closePopover();
    } else {
      openPopover();
    }
  }, [isOpen, openPopover, closePopover]);

  const data = useFloating({
    placement,
    open: isOpen,
    middleware: [offset(offsetOptions), flip(flipOptions), shift(shiftOptions)],
    ...floatingOptions,
    onOpenChange: (open, ...args) => {
      floatingOptions.onOpenChange?.(open, ...args);
      setIsOpen(open);
    },
    whileElementsMounted: (...args) => {
      floatingOptions.whileElementsMounted?.(...args);
      return autoUpdate(...args);
    },
  });

  const { context } = data;

  const click = useClick(context, {
    enabled: controlledIsOpen == null,
    ...clickOptions,
  });
  const dismiss = useDismiss(context, {
    ...dismissOptions,
  });
  const role = useRole(context, {
    ...roleOptions,
  });

  const interactions = useInteractions([click, dismiss, role]);

  const virtualElement = React.useMemo<VirtualElement | null>(() => {
    if (!getAnchorRect) {
      return null;
    }

    return {
      getBoundingClientRect: () => {
        return getAnchorRect() || new DOMRect();
      },
    };
  }, [getAnchorRect]);

  // 独自の参照要素が存在する場合、参照要素を設定
  React.useEffect(() => {
    if (getReferenceElement) {
      const referenceElement = getReferenceElement();
      if (referenceElement && data.refs.setReference) {
        data.refs.setReference(referenceElement);
        return;
      }
    }

    if (virtualElement && data.refs.setPositionReference) {
      data.refs.setPositionReference(virtualElement);
    }
  }, [virtualElement, data.refs, getReferenceElement]);

  return React.useMemo(
    () => ({
      isOpen,
      openPopover,
      closePopover,
      togglePopover,
      ...interactions,
      ...data,
      modal,
      focusManagerOptions,
    }),
    [isOpen, openPopover, closePopover, togglePopover, interactions, data, modal, focusManagerOptions]
  );
}
