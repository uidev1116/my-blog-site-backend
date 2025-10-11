import { forwardRef } from 'react';

interface ButtonProps<T extends React.ElementType> extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  as?: T;
  children?: React.ReactNode;
}

const ButtonWithoutRef = <T extends React.ElementType = 'button'>(
  { as, ...props }: ButtonProps<T> & Omit<React.ComponentPropsWithoutRef<T>, keyof ButtonProps<T>>,
  ref: React.ForwardedRef<HTMLButtonElement>
) => {
  const Component = as || 'button';
  return <Component ref={ref} {...props} />;
};

ButtonWithoutRef.displayName = 'Button';

const Button = forwardRef(ButtonWithoutRef) as <T extends React.ElementType = 'button'>(
  props: ButtonProps<T> & { ref?: React.ForwardedRef<HTMLButtonElement> }
) => JSX.Element;

export default Button;
