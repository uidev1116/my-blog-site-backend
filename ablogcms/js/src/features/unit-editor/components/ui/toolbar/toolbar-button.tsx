import classnames from 'classnames';
import { renderToStaticMarkup } from 'react-dom/server';
import { forwardRef } from 'react';
import { TooltipCommand } from '../../../../../components/tooltip';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface ToolbarButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /**
   * ラベル
   */
  label: string;

  /**
   * サイズ
   */
  size?: 'default' | 'large';

  /**
   * ツールチップのショートカットコマンド
   */
  commands?: React.ComponentPropsWithoutRef<typeof TooltipCommand>['commands'];

  children?: React.ReactNode;
}

const ToolbarButton = forwardRef<HTMLButtonElement, ToolbarButtonProps>(
  ({ commands, className, label, size = 'default', children, ...props }, ref) => {
    return (
      <button
        ref={ref}
        type="button"
        className={classnames('acms-admin-unit-toolbar-button', className, {
          [`acms-admin-unit-toolbar-button-${size}`]: size !== 'default',
        })}
        {...props}
        aria-label={label}
        data-tooltip-id="unit-editor-tooltip"
        data-tooltip-html={renderToStaticMarkup(
          <span>
            {label}
            {commands && <TooltipCommand commands={commands} />}
          </span>
        )}
      >
        {children}
      </button>
    );
  }
);

ToolbarButton.displayName = 'ToolbarButton';

export default ToolbarButton;
