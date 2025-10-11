import { useCallback, useRef } from 'react';
import useCallbackRef from '@hooks/use-callback-ref';
import useUpdateEffect from '@hooks/use-update-effect';
import useEffectOnce from '@hooks/use-effect-once';
import { useMapContext } from '../context/MapContext';

export interface UseMarkerOptions {
  position: { lat: number; lng: number };
  draggable?: boolean;
  onClick?: (event: google.maps.MouseEvent) => void;
  onDragEnd?: (event: google.maps.MouseEvent) => void;
}

export interface UseMarkerReturn {
  marker: google.maps.Marker | null;
}

const useMarker = ({ position, draggable = true, onClick, onDragEnd }: UseMarkerOptions): UseMarkerReturn => {
  const markerInstanceRef = useRef<google.maps.Marker | null>(null);
  const { mapInstance, googleInstance } = useMapContext();

  // コールバックの安定化
  const stableOnClick = useCallbackRef(onClick);
  const stableOnDragEnd = useCallbackRef(onDragEnd);

  // マーカーインスタンスの破棄
  const destroy = useCallback(() => {
    if (markerInstanceRef.current && googleInstance) {
      googleInstance.maps.event.clearInstanceListeners(markerInstanceRef.current);
      markerInstanceRef.current.setMap(null);
    }
    markerInstanceRef.current = null;
  }, [markerInstanceRef, googleInstance]);

  // マーカーの初期化
  const init = useCallback(() => {
    if (mapInstance === null) {
      return;
    }
    if (googleInstance === null) {
      return;
    }
    if (markerInstanceRef.current !== null) {
      return;
    }

    const positionLatLng = new googleInstance.maps.LatLng(position.lat, position.lng);

    // マーカー作成
    const marker = new googleInstance.maps.Marker({
      position: positionLatLng,
      map: mapInstance,
      draggable,
    });

    markerInstanceRef.current = marker;

    if (stableOnClick) {
      marker.addListener('click', stableOnClick);
    }
    if (stableOnDragEnd) {
      marker.addListener('dragend', stableOnDragEnd);
    }
  }, [
    position.lat,
    position.lng,
    draggable,
    stableOnClick,
    stableOnDragEnd,
    mapInstance,
    googleInstance,
    markerInstanceRef,
  ]);

  // 初期化とクリーンアップ
  useEffectOnce(() => {
    init();
    return () => {
      destroy();
    };
  });

  // 位置の更新
  useUpdateEffect(() => {
    if (markerInstanceRef.current && googleInstance) {
      const newPosition = new googleInstance.maps.LatLng(position.lat, position.lng);
      markerInstanceRef.current.setPosition(newPosition);
    }
  }, [position.lat, position.lng, googleInstance, markerInstanceRef]);

  // ドラッグ可能設定の更新
  useUpdateEffect(() => {
    if (markerInstanceRef.current) {
      markerInstanceRef.current.setDraggable(draggable);
    }
  }, [draggable, markerInstanceRef]);

  return {
    marker: markerInstanceRef.current,
  };
};

export default useMarker;
