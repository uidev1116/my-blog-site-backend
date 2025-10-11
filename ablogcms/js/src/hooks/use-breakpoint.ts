'use client';

import { getBreakpoints } from '../utils/breakpoint';
import useMediaQuery from './use-media-query';

/* -----------------------------------------------------------------------------
 * useBreakpoint
 * -----------------------------------------------------------------------------*/

export interface UseBreakpointOptions {
  fallback?: string | undefined;
  ssr?: boolean | undefined;
  getWindow?: () => typeof window | undefined;
  breakpoints?: string[] | undefined;
}

export default function useBreakpoint(options: UseBreakpointOptions = {}) {
  options.fallback ||= 'xs';
  let fallbackPassed = false;

  const breakpoints = Object.entries(getBreakpoints())
    .map(([breakpoint, value]) => {
      const item = {
        breakpoint,
        query: `(min-width: ${value})`,
        fallback: !fallbackPassed,
      };

      if (breakpoint === options.fallback) {
        fallbackPassed = true;
      }

      return item;
    })
    .filter(({ breakpoint }) => !options.breakpoints || options.breakpoints.includes(breakpoint));

  const fallback = breakpoints.map(({ fallback }) => fallback);

  const values = useMediaQuery(
    breakpoints.map((bp) => bp.query),
    { fallback, ssr: options.ssr }
  );

  // find highest matched breakpoint
  const index = values.lastIndexOf(true);

  return breakpoints[index]?.breakpoint ?? options.fallback;
}
