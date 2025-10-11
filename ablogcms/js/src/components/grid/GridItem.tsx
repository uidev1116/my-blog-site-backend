import React, { forwardRef } from 'react';
import classnames from 'classnames';
import { GridItemProps } from './types';
import { Slot } from '../slot';
import { getResponsiveClasses } from '../../utils/breakpoint';

const GridItem = forwardRef<HTMLDivElement, GridItemProps>(
  ({ col, className: classNameProp, style, children, asChild, ...props }, ref) => {
    const Component: React.ElementType = asChild ? Slot : 'div';
    const className = classnames(getResponsiveClasses('acms-admin-g-col', col), classNameProp);
    return (
      <Component ref={ref} className={className} style={style} {...props}>
        {children}
      </Component>
    );
  }
);

GridItem.displayName = 'GridItem';

export default GridItem;
