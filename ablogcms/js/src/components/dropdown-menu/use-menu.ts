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
  type Placement,
  useFloatingParentNodeId,
  useListItem,
  useFloatingTree,
  useFloatingNodeId,
  type ShiftOptions,
  type FlipOptions,
  type OffsetOptions,
  useHover,
  safePolygon,
  useListNavigation,
  useTypeahead,
  type VirtualElement,
  type Strategy,
  type FloatingFocusManagerProps,
  type UseFloatingOptions,
  type UseTypeaheadProps,
  type UseHoverProps,
  type UseClickProps,
  type UseRoleProps,
  type UseDismissProps,
  type UseListNavigationProps,
} from '@floating-ui/react';

export interface PositioningOptions {
  getAnchorRect?: () => DOMRect | null;
  x?: number;
  y?: number;
}

export interface MenuOptions {
  defaultIsOpen?: boolean;
  isOpen?: boolean;
  closeOnSelect?: boolean;
  placement?: Placement | ((isNested: boolean) => Placement);
  strategy?: Strategy;
  offsetOptions?: (isNested: boolean) => OffsetOptions | OffsetOptions;
  flipOptions?: FlipOptions;
  shiftOptions?: ShiftOptions;
  openDelay?: number;
  closeDelay?: number;
  onOpenChange?: (open: boolean) => void;
  getReferenceElement?: () => Element | null;
  getAnchorRect?: () => DOMRect | null;
  floatingOptions?: Partial<UseFloatingOptions>;
  hoverOptions?: Partial<UseHoverProps>;
  clickOptions?: Partial<UseClickProps>;
  roleOptions?: Partial<UseRoleProps>;
  dismissOptions?: Partial<UseDismissProps>;
  listNavigationOptions?: Partial<UseListNavigationProps>;
  typeaheadOptions?: Partial<UseTypeaheadProps>;
  focusManagerOptions?: Partial<FloatingFocusManagerProps>;
}

const defaultOffsetOptions = (isNested: boolean): OffsetOptions => {
  if (isNested) {
    return ({ elements }) => {
      const computedStyle = window.getComputedStyle(elements.floating);
      const paddingRight = parseFloat(computedStyle.paddingRight);
      return { mainAxis: paddingRight, alignmentAxis: -paddingRight };
    };
  }
  return { mainAxis: 4, alignmentAxis: 0 };
};

export default function useMenu({
  defaultIsOpen = false,
  placement: placementProp = (isNested) => (isNested ? 'right-start' : 'bottom-start'),
  isOpen: controlledIsOpen,
  closeOnSelect = true,
  offsetOptions: offsetOptionsProp = defaultOffsetOptions,
  flipOptions,
  shiftOptions,
  openDelay = 75,
  closeDelay,
  onOpenChange: setControlledIsOpen,
  floatingOptions = {},
  hoverOptions = {},
  clickOptions = {},
  roleOptions = {},
  dismissOptions = {},
  listNavigationOptions = {},
  typeaheadOptions = {},
  focusManagerOptions = {},
  getAnchorRect,
  getReferenceElement,
}: MenuOptions = {}) {
  const [uncontrolledIsOpen, setUncontrolledIsOpen] = React.useState(defaultIsOpen);
  const [hasFocusInside, setHasFocusInside] = React.useState(false);
  const [activeIndex, setActiveIndex] = React.useState<number | null>(null);

  const elementsRef = React.useRef<Array<HTMLButtonElement | null>>([]);
  const labelsRef = React.useRef<Array<string | null>>([]);

  const isOpen = controlledIsOpen ?? uncontrolledIsOpen;
  const setIsOpen = setControlledIsOpen ?? setUncontrolledIsOpen;

  const openMenu = React.useCallback(() => {
    setIsOpen(true);
  }, [setIsOpen]);

  const closeMenu = React.useCallback(() => {
    setIsOpen(false);
  }, [setIsOpen]);

  const toggleMenu = React.useCallback(() => {
    if (isOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  }, [isOpen, openMenu, closeMenu]);

  const tree = useFloatingTree();
  const nodeId = useFloatingNodeId();
  const parentId = useFloatingParentNodeId();
  const listItem = useListItem();

  const isNested = parentId != null;
  const offsetOptions = typeof offsetOptionsProp === 'function' ? offsetOptionsProp(isNested) : offsetOptionsProp;
  const placement = typeof placementProp === 'function' ? placementProp(isNested) : placementProp;

  const data = useFloating<HTMLButtonElement>({
    nodeId,
    open: isOpen,
    placement,
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

  const hover = useHover(context, {
    enabled: isNested,
    delay: { open: openDelay, close: closeDelay },
    ...hoverOptions,
    handleClose: safePolygon({ blockPointerEvents: true }),
  });
  const click = useClick(context, {
    event: 'mousedown',
    toggle: !isNested,
    ignoreMouse: isNested,
    ...clickOptions,
  });
  const role = useRole(context, {
    role: 'menu',
    ...roleOptions,
  });
  const dismiss = useDismiss(context, { bubbles: true, ...dismissOptions });
  const listNavigation = useListNavigation(context, {
    listRef: elementsRef,
    activeIndex,
    nested: isNested,
    loop: true,
    focusItemOnHover: false,
    ...listNavigationOptions,
    onNavigate: (...args) => {
      listNavigationOptions.onNavigate?.(...args);
      setActiveIndex(args[0]);
    },
  });
  const typeahead = useTypeahead(context, {
    listRef: labelsRef,
    activeIndex,
    ...typeaheadOptions,
    onMatch: (...args) => {
      typeaheadOptions.onMatch?.(...args);
      if (isOpen) {
        setActiveIndex(args[0]);
      }
    },
  });

  const interactions = useInteractions([hover, click, role, dismiss, listNavigation, typeahead]);

  // Event emitter allows you to communicate across tree components.
  // This effect closes all menus when an item gets selected anywhere
  // in the tree.
  React.useEffect(() => {
    if (!tree) return;

    function handleSelect(event: { closeOnSelect?: boolean }) {
      if (event.closeOnSelect === false) {
        return;
      }
      if (closeOnSelect === false) {
        return;
      }

      closeMenu();
    }

    function onSubMenuOpen(event: { nodeId: string; parentId: string }) {
      if (event.nodeId !== nodeId && event.parentId === parentId) {
        closeMenu();
      }
    }

    tree.events.on('select', handleSelect);
    tree.events.on('menuopen', onSubMenuOpen);

    return () => {
      tree.events.off('select', handleSelect);
      tree.events.off('menuopen', onSubMenuOpen);
    };
  }, [tree, nodeId, parentId, closeMenu, closeOnSelect]);

  React.useEffect(() => {
    if (isOpen && tree) {
      tree.events.emit('menuopen', { parentId, nodeId });
    }
  }, [tree, isOpen, nodeId, parentId]);

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
      openMenu,
      closeMenu,
      toggleMenu,
      ...interactions,
      ...data,
      listItem,
      hasFocusInside,
      setHasFocusInside,
      activeIndex,
      setActiveIndex,
      elementsRef,
      labelsRef,
      nodeId,
      parentId,
      isNested,
      closeOnSelect,
      focusManagerOptions,
    }),
    [
      isOpen,
      openMenu,
      closeMenu,
      toggleMenu,
      interactions,
      data,
      hasFocusInside,
      setHasFocusInside,
      activeIndex,
      setActiveIndex,
      listItem,
      nodeId,
      parentId,
      isNested,
      closeOnSelect,
      focusManagerOptions,
    ]
  );
}
