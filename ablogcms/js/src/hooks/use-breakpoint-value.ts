'use client';

import { type Dict } from '../types/utils';
import { getBreakpoints } from '../utils/breakpoint';
import useBreakpoint, { type UseBreakpointOptions } from './use-breakpoint';

/* -----------------------------------------------------------------------------
 * useBreakpoint Value
 * -----------------------------------------------------------------------------*/

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function normalizeValue(value: any): any {
  if (Array.isArray(value)) {
    const breakpoints = getBreakpoints();
    return value.reduce((acc, current, index) => {
      const key = Object.keys(breakpoints)[index];
      if (current != null) acc[key] = current;
      return acc;
    }, {});
  }
  return value;
}

export type UseBreakpointValueOptions = Omit<UseBreakpointOptions, 'breakpoints'>;

type Value<T> = Dict<T> | Array<T | null>;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function useBreakpointValue<T = any>(value: Value<T>, opts?: UseBreakpointValueOptions): T | undefined {
  const normalized = normalizeValue(value);
  const breakpoint = useBreakpoint({
    breakpoints: Object.keys(normalized),
    ...opts,
  });

  return normalized[breakpoint];
}
