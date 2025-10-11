import { useRef, useCallback } from 'react';
import useCallbackRef from '@hooks/use-callback-ref';
import useUpdateEffect from '@hooks/use-update-effect';
import useEffectOnce from '@hooks/use-effect-once';
import { useMapContext } from '../context/MapContext';

export interface UseMapOptions {
  center: { lat: number; lng: number };
  zoom: number;
  onInit?: (map: google.maps.Map) => void;
  onCenterChange?: (center: { lat: number; lng: number }) => void;
  onZoomChange?: (zoom: number) => void;
}

export interface UseMapReturn<T extends HTMLElement> {
  mapRef: React.RefObject<T>;
  map: google.maps.Map | null;
}

const useMap = <T extends HTMLElement>({
  center,
  zoom,
  onInit,
  onCenterChange,
  onZoomChange,
}: UseMapOptions): UseMapReturn<T> => {
  const mapRef = useRef<T>(null);
  const { mapInstance, setMapInstance, googleInstance } = useMapContext();

  // コールバックの安定化
  const stableOnCenterChange = useCallbackRef(onCenterChange);
  const stableOnZoomChange = useCallbackRef(onZoomChange);

  // マップインスタンスの破棄
  const destroy = useCallback(() => {
    if (mapInstance && googleInstance) {
      googleInstance.maps.event.clearInstanceListeners(mapInstance);
    }
    setMapInstance(null);
  }, [mapInstance, setMapInstance, googleInstance]);

  // マップの初期化
  const init = useCallback(async () => {
    if (!mapRef.current) {
      return;
    }
    if (!googleInstance) {
      return;
    }
    if (mapInstance) {
      return;
    }

    const centerLatLng = new googleInstance.maps.LatLng(center.lat, center.lng);

    // マップ作成
    const map = new googleInstance.maps.Map<T>(mapRef.current, {
      zoom,
      center: centerLatLng,
      mapTypeId: googleInstance.maps.MapTypeId.ROADMAP,
      gestureHandling: 'cooperative',
    });

    setMapInstance(map);

    // イベントリスナー設定
    map.addListener('center_changed', () => {
      const newCenter = map.getCenter();
      if (newCenter && stableOnCenterChange) {
        stableOnCenterChange({
          lat: newCenter.lat(),
          lng: newCenter.lng(),
        });
      }
    });

    map.addListener('zoom_changed', () => {
      const newZoom = map.getZoom();
      if (newZoom !== undefined && stableOnZoomChange) {
        stableOnZoomChange(newZoom);
      }
    });

    if (onInit) {
      onInit(map);
    }
  }, [
    center.lat,
    center.lng,
    zoom,
    stableOnCenterChange,
    stableOnZoomChange,
    mapInstance,
    setMapInstance,
    googleInstance,
    onInit,
  ]);

  // 初期化とクリーンアップ
  useEffectOnce(() => {
    init();
    return () => {
      destroy();
    };
  });

  // 中心座標の更新
  useUpdateEffect(() => {
    if (mapInstance && googleInstance) {
      const newCenter = new googleInstance.maps.LatLng(center.lat, center.lng);
      mapInstance.setCenter(newCenter);
    }
  }, [center.lat, center.lng, mapInstance, googleInstance]);

  // ズームの更新
  useUpdateEffect(() => {
    if (mapInstance) {
      mapInstance.setZoom(zoom);
    }
  }, [zoom, mapInstance]);

  return {
    mapRef,
    map: mapInstance,
  };
};

export default useMap;
