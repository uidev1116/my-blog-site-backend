import { HTMLProps, forwardRef } from 'react';
import { cn } from '../../lib/utils';

export type SurfaceProps = HTMLProps<HTMLDivElement> & {
  withShadow?: boolean;
  withBorder?: boolean;
};

export const Surface = forwardRef<HTMLDivElement, SurfaceProps>(({ children, className, ...props }, ref) => {
  const surfaceClass = cn('acms-admin-block-editor-surface', className);

  return (
    <div className={surfaceClass} {...props} ref={ref}>
      {children}
    </div>
  );
});

Surface.displayName = 'Surface';
