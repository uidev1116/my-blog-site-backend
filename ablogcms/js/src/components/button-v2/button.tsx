import { forwardRef } from 'react';
import classnames from 'classnames';
import { Slot } from '../slot';
import useMergeRefs from '../../hooks/use-merge-refs';
import type { PolymorphicProps } from '../../types/polymorphic';

/**
 * ボタンのバリアント型
 */
export type ButtonVariant = 'default' | 'filled' | 'outlined' | 'unit-insert';

/**
 * ボタンのサイズ
 */
export type ButtonSize = 'small' | 'default' | 'large';

/**
 * ボタンコンポーネントのプロパティ
 */
export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, PolymorphicProps {
  /**
   * ボタンの見た目のバリアント
   * @default 'default'
   */
  variant?: ButtonVariant;

  /**
   * ボタンのサイズ
   * @default 'default'
   */
  size?: ButtonSize;

  /**
   * ボタンのシャドウ
   * @default false
   */
  elevated?: boolean;

  /**
   * 子要素
   */
  children?: React.ReactNode;
}

const BASE_CLASS_NAME = 'acms-admin-btn-v2';

/**
 * ボタンコンポーネント
 * @param props - ボタンコンポーネントのプロパティ
 * @param ref - 転送する参照
 * @returns ボタンコンポーネント
 */
const ButtonV2 = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ children, variant = 'default', size = 'default', asChild, className, elevated = false, ...props }, ref) => {
    const Component = asChild ? Slot : 'button';

    // ベースクラスとバリアントに応じたクラスを結合
    const buttonClassName = classnames(
      BASE_CLASS_NAME,
      {
        [`${BASE_CLASS_NAME}-${variant}`]: variant !== 'default',
        [`${BASE_CLASS_NAME}-${size}`]: size !== 'default',
        [`${BASE_CLASS_NAME}-elevated`]: elevated,
      },
      className
    );

    return (
      <Component ref={useMergeRefs(ref)} className={buttonClassName} {...props}>
        {children}
      </Component>
    );
  }
);

ButtonV2.displayName = 'ButtonV2';

export default ButtonV2;
