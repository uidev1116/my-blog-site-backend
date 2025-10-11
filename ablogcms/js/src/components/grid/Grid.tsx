import React, { forwardRef } from 'react';
import classnames from 'classnames';
import { GridProps } from './types';
import { Slot } from '../slot';
import { getResponsiveClasses } from '../../utils/breakpoint';

const Grid = forwardRef<HTMLDivElement, GridProps>(
  ({ columns, gap, rows, className: classNameProp, style, children, asChild, ...props }, ref) => {
    const Component: React.ElementType = asChild ? Slot : 'div';
    const gridStyle: React.CSSProperties = {
      ...style,
      ...(typeof columns === 'number' ? { '--acms-admin-columns': columns } : {}),
      ...(typeof gap === 'string' ? { '--acms-admin-gap': gap } : {}),
      ...(typeof rows === 'number' ? { '--acms-admin-rows': rows } : {}),
    };
    const className = classnames(
      'acms-admin-cssgrid',
      getResponsiveClasses('acms-admin-g-cols', columns),
      classNameProp
    );

    return (
      <Component ref={ref} className={className} style={gridStyle} {...props}>
        {children}
      </Component>
    );
  }
);

Grid.displayName = 'Grid';

export default Grid;
