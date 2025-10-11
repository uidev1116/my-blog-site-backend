import useInfoWindow, { UseInfoWindowOptions } from '../hooks/useInfoWindow';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface InfoWindowProps extends UseInfoWindowOptions {}

const InfoWindow = (props: InfoWindowProps) => {
  useInfoWindow(props);

  return null;
};

export default InfoWindow;
