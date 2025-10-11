import { forwardRef } from 'react';
import classnames from 'classnames';
import { Slot } from '../slot';
import type { StackProps } from './types';
import { getResponsiveClasses } from '../../utils/breakpoint';

const Stack = forwardRef<HTMLDivElement, StackProps>(
  (
    {
      asChild,
      direction,
      spacing,
      align,
      justify,
      wrap,
      display,
      children,
      className: classNameProp = '',
      style,
      ...props
    },
    ref
  ) => {
    const Component = asChild ? Slot : 'div';

    const className = classnames(
      'acms-admin-stack',
      getResponsiveClasses('acms-admin-flex', direction),
      getResponsiveClasses('acms-admin-align-items', align),
      getResponsiveClasses('acms-admin-justify-content', justify),
      getResponsiveClasses('acms-admin-flex', wrap),
      getResponsiveClasses('acms-admin-d', display),
      classNameProp
    );

    return (
      <Component
        ref={ref}
        className={className}
        style={{ '--acms-admin-stack-spacing': spacing, ...style } as React.CSSProperties}
        {...props}
      >
        {children}
      </Component>
    );
  }
);

Stack.displayName = 'Stack';

export default Stack;
