import useMarker, { UseMarkerOptions } from '../hooks/useMarker';

interface MarkerProps extends UseMarkerOptions {
  children?: React.ReactNode;
}

const Marker = ({ children, ...options }: MarkerProps) => {
  useMarker(options);

  return children;
};

export default Marker;
