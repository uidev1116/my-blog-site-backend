import { useCallback } from 'react';
import { useMapContext } from '../context/MapContext';

interface UseGeocodingReturn {
  geocode: (address: string) => Promise<{ lat: number; lng: number } | null>;
}

const useGeocoding = (): UseGeocodingReturn => {
  const { googleInstance } = useMapContext();

  const geocode = useCallback(
    async (address: string): Promise<{ lat: number; lng: number } | null> => {
      try {
        if (!googleInstance) {
          throw new Error('Google Maps API is not loaded');
        }

        const geocoder = new googleInstance.maps.Geocoder();

        const results = await new Promise<google.maps.GeocoderResult[]>((resolve, reject) => {
          geocoder.geocode({ address }, (results, status) => {
            if (status === googleInstance.maps.GeocoderStatus.OK && results) {
              resolve(results);
            } else {
              reject(new Error(`Geocoding failed: ${status}`));
            }
          });
        });

        if (results.length > 0) {
          const { location } = results[0].geometry;
          return { lat: location.lat(), lng: location.lng() };
        }

        return null;
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Geocoding error:', error);
        throw error;
      }
    },
    [googleInstance]
  );

  return {
    geocode,
  };
};

export default useGeocoding;
