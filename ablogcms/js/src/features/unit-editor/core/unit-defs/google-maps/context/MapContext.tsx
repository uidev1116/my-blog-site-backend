import React, { createContext, useContext, useMemo, useEffect, useState } from 'react';
import loadGoogleMap from '../../../../../../lib/google-maps/loader';

interface MapContextValue {
  mapInstance: google.maps.Map | null;
  setMapInstance: React.Dispatch<React.SetStateAction<google.maps.Map | null>>;
  googleInstance: typeof google | null;
}

const MapContext = createContext<MapContextValue | null>(null);

export const useMapContext = () => {
  const context = useContext(MapContext);
  if (!context) {
    throw new Error('useMapContext must be used within a MapProvider');
  }
  return context;
};

interface MapProviderProps {
  children: React.ReactNode;
  apiKey: string;
  onLoad?: (googleInstance: typeof google) => void;
}

export const MapProvider = ({ children, apiKey, onLoad }: MapProviderProps) => {
  const [mapInstance, setMapInstance] = useState<google.maps.Map | null>(null);
  const [googleInstance, setGoogleInstance] = useState<typeof google | null>(null);

  useEffect(() => {
    (async () => {
      // Google Maps API読み込み
      const google = await loadGoogleMap(apiKey);
      setGoogleInstance(google);
      onLoad?.(google);
    })();

    return () => {
      setGoogleInstance(null);
    };
  }, [apiKey, onLoad]);

  const value = useMemo(
    () => ({
      mapInstance,
      setMapInstance,
      googleInstance,
    }),
    [mapInstance, googleInstance]
  );

  if (!googleInstance) {
    return null;
  }

  return <MapContext.Provider value={value}>{children}</MapContext.Provider>;
};
