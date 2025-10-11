export type Breakpoint = 'xs' | 'sm' | 'md' | 'lg' | 'xl';
export type BreakpointMap<T extends string | number> = Partial<Record<Breakpoint, T>>;
export type ResponsiveValue<T extends string | number> = T | BreakpointMap<T>;
