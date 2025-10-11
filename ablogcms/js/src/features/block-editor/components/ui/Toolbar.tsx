import { ButtonHTMLAttributes, HTMLProps, forwardRef, useRef } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import classnames from 'classnames';
import { TooltipCommand } from '@components/tooltip';
import { Surface } from '@features/block-editor/components/ui/Surface';
import useMergeRefs from '@hooks/use-merge-refs';
import useToolbar, { UseToolbarOptions } from '../../../../hooks/use-toolbar';

export interface ToolbarRootProps extends UseToolbarOptions, HTMLProps<HTMLDivElement> {
  shouldShowContent?: boolean;
}

const ToolbarRoot = forwardRef<HTMLDivElement, ToolbarRootProps>(
  ({ shouldShowContent = true, children, orientation, direction, className, ...props }, forwardRef) => {
    const ref = useRef<HTMLDivElement>(null);
    const setRef = useMergeRefs(forwardRef, ref);
    const { toolbarProps } = useToolbar({ orientation, direction });
    const toolbarClassName = classnames('acms-admin-block-editor-toolbar', className);

    return (
      shouldShowContent && (
        <Surface className={toolbarClassName} {...toolbarProps({ ref: setRef, ...props })}>
          {children}
        </Surface>
      )
    );
  }
);

ToolbarRoot.displayName = 'Toolbar';

export type ToolbarDividerProps = {
  horizontal?: boolean;
} & HTMLProps<HTMLDivElement>;

const ToolbarDivider = forwardRef<HTMLDivElement, ToolbarDividerProps>(({ horizontal, className, ...rest }, ref) => {
  const dividerClassName = classnames(
    'acms-admin-block-editor-toolbar-divider',
    horizontal ? 'acms-admin-block-editor-toolbar-divider-horizontal' : '',
    className
  );

  return <div className={dividerClassName} ref={ref} {...rest} />;
});

ToolbarDivider.displayName = 'Toolbar.Divider';

export type ButtonVariant = 'primary' | 'secondary';
export type ButtonSize = 'medium' | 'small';

export type ToolbarButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  /**
   * ボタンのバリエーション
   * @default primary
   */
  variant?: ButtonVariant;
  active?: boolean;
  activeClassname?: string;
  tooltip?: string;
  tooltipShortcut?: string[];
  /**
   * ボタンのサイズ
   * @default medium
   */
  size?: ButtonSize;
};

const ToolbarButton = forwardRef<HTMLButtonElement, ToolbarButtonProps>(
  (
    {
      active,
      children,
      size = 'medium',
      variant = 'primary',
      className,
      tooltip,
      tooltipShortcut,
      activeClassname,
      ...rest
    },
    ref
  ) => {
    const buttonClassName = classnames(
      'acms-admin-block-editor-button',

      variant === 'primary' &&
        classnames(
          'acms-admin-block-editor-button-primary',
          active && classnames('acms-admin-block-editor-button-active', activeClassname)
        ),

      variant === 'secondary' &&
        classnames(
          'acms-admin-block-editor-button-secondary',
          active && classnames('acms-admin-block-editor-button-active', activeClassname)
        ),

      size === 'medium' && 'acms-admin-block-editor-button-icon',
      size === 'small' && 'acms-admin-block-editor-button-icon-small',

      className
    );

    return (
      <button
        type="button"
        className={buttonClassName}
        ref={ref}
        {...rest}
        aria-pressed={active}
        data-tooltip-id="block-editor-shared-tooltip"
        data-tooltip-html={
          tooltip &&
          renderToStaticMarkup(
            <>
              {tooltip}
              <TooltipCommand commands={tooltipShortcut ?? []} />
            </>
          )
        }
      >
        {children}
      </button>
    );
  }
);

ToolbarButton.displayName = 'ToolbarButton';

export const Toolbar = Object.assign(ToolbarRoot, {
  Divider: ToolbarDivider,
  Button: ToolbarButton,
});
