import { forwardRef } from 'react';
import classnames from 'classnames';
import { Slot } from '../slot';
import { PolymorphicProps } from '../../types/polymorphic';

interface VisuallyHiddenProps extends React.HTMLAttributes<HTMLSpanElement>, PolymorphicProps {
  children: React.ReactNode;
}

const VisuallyHidden = forwardRef<HTMLSpanElement, VisuallyHiddenProps>(
  ({ asChild, children, className, ...props }, ref) => {
    const Component = asChild ? Slot : 'span';

    return (
      <Component ref={ref} className={classnames('acms-admin-hide-visually', className)} {...props}>
        {children}
      </Component>
    );
  }
);

export default VisuallyHidden;
