import type { PolymorphicProps } from '../../types/polymorphic';
import type { ResponsiveValue } from '../../types/breakpoint';

export interface StackProps extends React.HTMLAttributes<HTMLElement>, PolymorphicProps {
  direction?: ResponsiveValue<'column' | 'row' | 'column-reverse' | 'row-reverse'>;
  spacing?: string;
  align?: ResponsiveValue<'normal' | 'center' | 'stretch' | 'start' | 'end'>;
  justify?: ResponsiveValue<'normal' | 'start' | 'end' | 'center' | 'between' | 'around' | 'evenly'>;
  wrap?: ResponsiveValue<'wrap' | 'nowrap' | 'wrap-reverse'>;
  display?: ResponsiveValue<'flex' | 'inline-flex'>;
  children: React.ReactNode;
  asChild?: boolean;
}
