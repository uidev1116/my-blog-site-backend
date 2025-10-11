import { memo } from 'react';
import classnames from 'classnames';
import { Icon as BaseIcon } from '@components/icon';

export interface IconProps extends React.ComponentPropsWithoutRef<typeof BaseIcon> {
  size?: 'sm' | 'md' | 'lg';
}

export const Icon = memo(({ size = 'md', ...props }: IconProps) => {
  return (
    <BaseIcon
      className={classnames('acms-admin-block-editor-icon', {
        [`acms-admin-block-editor-icon-${size}`]: size !== 'md',
      })}
      aria-hidden="true"
      {...props}
    />
  );
});

Icon.displayName = 'Icon';
