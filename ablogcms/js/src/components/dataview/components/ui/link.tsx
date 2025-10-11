import { forwardRef } from 'react';

interface LinkProps<T extends React.ElementType> {
  as?: T;
}

const LinkWithoutRef = <T extends React.ElementType = 'a'>(
  { as, ...props }: LinkProps<T> & Omit<React.ComponentPropsWithoutRef<T>, keyof LinkProps<T>>,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  ref: React.ForwardedRef<any>
) => {
  const Component = as || 'a';
  return <Component ref={ref} {...props} />;
};

LinkWithoutRef.displayName = 'Link';

const Link = forwardRef(LinkWithoutRef) as <T extends React.ElementType = 'a'>(
  { as, ...props }: LinkProps<T> & Omit<React.ComponentPropsWithoutRef<T>, keyof LinkProps<T>>,
  ref: React.Ref<T>
) => JSX.Element;

export default Link;
