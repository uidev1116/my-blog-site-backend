import { forwardRef } from 'react';
import classnames from 'classnames';
import type { MaterialSymbol } from '../../types/material-symbols';

export interface IconProps extends React.HTMLAttributes<HTMLSpanElement> {
  name: MaterialSymbol;
}

const Icon = forwardRef<HTMLSpanElement, IconProps>(({ name, className, ...props }, ref) => {
  return (
    <span className={classnames('material-symbols-outlined', className)} aria-hidden="true" {...props} ref={ref}>
      {name}
    </span>
  );
});

Icon.displayName = 'Icon';

export default Icon;
