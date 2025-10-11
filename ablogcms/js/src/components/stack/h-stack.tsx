import { forwardRef } from 'react';
import Stack from './stack';

type StackProps = React.ComponentPropsWithRef<typeof Stack>;
interface HStackProps extends Omit<StackProps, 'direction'> {
  direction?: Exclude<StackProps['direction'], 'column' | 'column-reverse'>;
}

const HStack = forwardRef<HTMLDivElement, HStackProps>((props, ref) => <Stack ref={ref} direction="row" {...props} />);
HStack.displayName = 'HStack';

export default HStack;
