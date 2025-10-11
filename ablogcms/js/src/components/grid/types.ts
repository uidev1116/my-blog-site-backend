import type { PolymorphicProps } from '../../types/polymorphic';
import type { ResponsiveValue } from '../../types/breakpoint';

export interface GridProps extends React.HTMLAttributes<HTMLElement>, PolymorphicProps {
  columns?: ResponsiveValue<number>;
  gap?: ResponsiveValue<string>;
  rows?: number;
  children: React.ReactNode;
}

export interface GridItemProps extends React.HTMLAttributes<HTMLElement>, PolymorphicProps {
  col: ResponsiveValue<number>;
  children: React.ReactNode;
}
