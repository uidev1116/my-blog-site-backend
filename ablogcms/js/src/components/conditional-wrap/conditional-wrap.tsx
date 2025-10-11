interface ConditionalWrapProps {
  condition: boolean;
  wrap: (children: React.ReactNode) => React.ReactNode;
  children: React.ReactNode;
}

const ConditionalWrap = ({ condition, wrap, children }: ConditionalWrapProps): React.ReactNode =>
  condition ? wrap(children) : children;

export default ConditionalWrap;
