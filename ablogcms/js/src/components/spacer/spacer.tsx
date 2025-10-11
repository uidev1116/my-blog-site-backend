import { forwardRef } from 'react';

/**
 * Spacerコンポーネントは余白を制御するための最終手段として使用してください。
 * 可能な限り、marginやpaddingなどのCSSプロパティを使用することを推奨します。
 * レイアウトの一貫性を保つため、デザインシステムで定義された余白の使用を検討してください。
 */
interface SpacerProps extends React.HTMLAttributes<HTMLSpanElement> {
  size?: number;
  axis?: 'vertical' | 'horizontal';
}

const Spacer = forwardRef<HTMLSpanElement, SpacerProps>(({ size, axis, style = {}, ...props }, ref) => {
  const width = axis === 'vertical' ? 1 : size;
  const height = axis === 'horizontal' ? 1 : size;
  return (
    <span
      ref={ref}
      style={{
        display: 'block',
        width,
        minWidth: width,
        height,
        minHeight: height,
        ...style,
      }}
      {...props}
    />
  );
});

Spacer.displayName = 'Spacer';

export default Spacer;
