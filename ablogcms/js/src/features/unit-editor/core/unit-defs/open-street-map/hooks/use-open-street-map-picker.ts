import { useEffect, useRef, useCallback } from 'react';
import Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import useCallbackRef from '@hooks/use-callback-ref';
import useUpdateEffect from '@hooks/use-update-effect';
import icon from 'leaflet/dist/images/marker-icon.png';
import icon2x from 'leaflet/dist/images/marker-icon-2x.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import useEffectOnce from '@hooks/use-effect-once';

// 既存実装と同様の設定
delete (Leaflet.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl;
Leaflet.Icon.Default.mergeOptions({
  iconUrl: icon,
  iconRetinaUrl: icon2x,
  shadowUrl: iconShadow,
});

interface UseOpenStreetMapPickerProps {
  lat: number;
  lng: number;
  zoom: number;
  msg?: string;
  onMarkerChange?: (values: { lat: number; lng: number }) => void;
  onZoomChange?: (zoom: number) => void;
}

interface UseOpenStreetMapPickerReturn {
  mapRef: React.RefObject<HTMLDivElement>;
}

const useOpenStreetMapPicker = ({
  lat,
  lng,
  zoom,
  msg,
  onMarkerChange,
  onZoomChange,
}: UseOpenStreetMapPickerProps): UseOpenStreetMapPickerReturn => {
  const mapRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<Leaflet.Map | null>(null);
  const markerRef = useRef<Leaflet.Marker | null>(null);

  // 汎用フックを使用してコールバックを安定化
  const stableOnMarkerChange = useCallbackRef(onMarkerChange);
  const stableOnZoomChange = useCallbackRef(onZoomChange);

  const init = useCallback(() => {
    if (!mapRef.current || mapInstanceRef.current) return;

    const map = Leaflet.map(mapRef.current).setView([lat, lng], zoom);
    const marker = Leaflet.marker([lat, lng], { draggable: true });

    Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    marker.addTo(map);

    // イベントリスナーの設定
    map.on('zoomend', () => {
      stableOnZoomChange(map.getZoom());
    });

    marker.on('drag', () => {
      const position = marker.getLatLng();

      stableOnMarkerChange({ lat: position.lat, lng: position.lng });
    });

    marker.on('dragend', () => {
      const position = marker.getLatLng();
      map.panTo(position);
    });

    mapInstanceRef.current = map;
    markerRef.current = marker;
  }, [lat, lng, zoom, stableOnMarkerChange, stableOnZoomChange]);

  const destroy = useCallback(() => {
    if (mapInstanceRef.current) {
      mapInstanceRef.current.remove();
      mapInstanceRef.current.off();
      mapInstanceRef.current = null;
      markerRef.current = null;
    }
  }, []);

  const updatePosition = useCallback((newLat: number, newLng: number, newZoom?: number) => {
    if (!mapInstanceRef.current || !markerRef.current) return;

    const map = mapInstanceRef.current;
    const marker = markerRef.current;
    const zoomLevel = newZoom !== undefined ? newZoom : map.getZoom();

    map.setView([newLat, newLng], zoomLevel);
    marker.setLatLng([newLat, newLng]);
  }, []);

  // 初回マウントのみ実行
  useEffectOnce(() => {
    init();
    return () => {
      destroy();
    };
  });

  // 初回以外の更新時に位置を更新
  useUpdateEffect(() => {
    updatePosition(lat, lng, zoom);
  }, [lat, lng, zoom, updatePosition]);

  // 初回以外の更新時にメッセージを更新
  useEffect(() => {
    if (!markerRef.current) return;

    const marker = markerRef.current;
    if (msg) {
      marker.bindPopup(msg);
      marker.setPopupContent(msg);
    } else {
      marker.closePopup();
      marker.unbindPopup();
    }
  }, [msg]);

  return {
    mapRef,
  };
};

export default useOpenStreetMapPicker;
