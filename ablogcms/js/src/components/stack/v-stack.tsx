import { forwardRef } from 'react';
import Stack from './stack';

type StackProps = React.ComponentPropsWithRef<typeof Stack>;
interface VStackProps extends Omit<StackProps, 'direction'> {
  direction?: Exclude<StackProps['direction'], 'row' | 'row-reverse'>;
}

const VStack = forwardRef<HTMLDivElement, VStackProps>((props, ref) => (
  <Stack ref={ref} direction="column" {...props} />
));
VStack.displayName = 'VStack';

export default VStack;
