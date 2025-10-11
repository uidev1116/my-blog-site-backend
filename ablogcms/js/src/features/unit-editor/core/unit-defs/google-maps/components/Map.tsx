import classnames from 'classnames';
import useMap, { UseMapOptions } from '../hooks/useMap';

interface MapProps extends UseMapOptions {
  className?: string;
  style?: React.CSSProperties;
  children?: React.ReactNode;
}

const Map = ({ className, style, children, ...options }: MapProps) => {
  const { mapRef, map } = useMap<HTMLDivElement>(options);

  return (
    <div className="acms-admin-gmap-container">
      <div
        ref={mapRef}
        className={classnames('acms-admin-gmap-view', className)}
        style={{ width: '100%', aspectRatio: '1/1', ...style }}
      />
      {map && children}
    </div>
  );
};

export default Map;
