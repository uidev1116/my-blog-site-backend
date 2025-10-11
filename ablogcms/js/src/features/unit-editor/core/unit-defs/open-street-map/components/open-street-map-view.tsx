import classnames from 'classnames';
import useOpenStreetMapPicker from '../hooks/use-open-street-map-picker';

export interface OpenStreetMapViewProps {
  lat: number;
  lng: number;
  zoom: number;
  msg?: string;
  onMarkerChange?: (value: { lat: number; lng: number }) => void;
  onZoomChange?: (zoom: number) => void;
  className?: string;
  style?: React.CSSProperties;
}

const OpenStreetMapView = ({
  lat,
  lng,
  zoom,
  msg,
  onMarkerChange = () => {},
  onZoomChange = () => {},
  className,
  style,
}: OpenStreetMapViewProps) => {
  const { mapRef } = useOpenStreetMapPicker({
    lat,
    lng,
    zoom,
    msg,
    onMarkerChange,
    onZoomChange,
  });

  return (
    <div className="acms-admin-osm-container">
      <div ref={mapRef} className={classnames('acms-admin-osm-view', className)} style={style} />
    </div>
  );
};

export default OpenStreetMapView;
