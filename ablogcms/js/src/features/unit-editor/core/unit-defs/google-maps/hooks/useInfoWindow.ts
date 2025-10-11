import { useRef, useCallback } from 'react';
import useCallbackRef from '@hooks/use-callback-ref';
import useUpdateEffect from '@hooks/use-update-effect';
import useEffectOnce from '@hooks/use-effect-once';
import { useMapContext } from '../context/MapContext';

export interface UseInfoWindowOptions {
  position: { lat: number; lng: number };
  content: string;
  isOpen?: boolean;
  onClose?: () => void;
}

export interface UseInfoWindowReturn {
  infoWindow: google.maps.InfoWindow | null;
}

const useInfoWindow = ({ position, content, isOpen = false, onClose }: UseInfoWindowOptions): UseInfoWindowReturn => {
  const infoWindowInstanceRef = useRef<google.maps.InfoWindow | null>(null);
  const { mapInstance, googleInstance } = useMapContext();

  // コールバックの安定化
  const stableOnClose = useCallbackRef(onClose);

  // 情報ウィンドウインスタンスの破棄
  const destroy = useCallback(() => {
    if (infoWindowInstanceRef.current && googleInstance) {
      googleInstance.maps.event.clearInstanceListeners(infoWindowInstanceRef.current);
      infoWindowInstanceRef.current.close();
    }
    infoWindowInstanceRef.current = null;
  }, [infoWindowInstanceRef, googleInstance]);

  // 情報ウィンドウの初期化
  const init = useCallback(() => {
    if (mapInstance === null) {
      return;
    }
    if (googleInstance === null) {
      return;
    }
    if (infoWindowInstanceRef.current !== null) {
      return;
    }

    const positionLatLng = new googleInstance.maps.LatLng(position.lat, position.lng);

    // 情報ウィンドウ作成
    const infoWindow = new googleInstance.maps.InfoWindow({
      content,
      position: positionLatLng,
    });

    infoWindowInstanceRef.current = infoWindow;

    // 閉じるイベント設定
    if (stableOnClose) {
      infoWindow.addListener('closeclick', stableOnClose);
    }
  }, [position.lat, position.lng, content, stableOnClose, mapInstance, googleInstance, infoWindowInstanceRef]);

  // 初期化とクリーンアップ
  useEffectOnce(() => {
    init();
    return () => {
      destroy();
    };
  });

  // 位置の更新
  useUpdateEffect(() => {
    if (infoWindowInstanceRef.current && googleInstance) {
      const newPosition = new googleInstance.maps.LatLng(position.lat, position.lng);
      infoWindowInstanceRef.current.setPosition(newPosition);
    }
  }, [position.lat, position.lng, googleInstance, infoWindowInstanceRef]);

  // コンテンツの更新
  useUpdateEffect(() => {
    if (infoWindowInstanceRef.current) {
      infoWindowInstanceRef.current.setContent(content);
    }
  }, [content, infoWindowInstanceRef]);

  // 開閉状態の更新
  useUpdateEffect(() => {
    if (infoWindowInstanceRef.current && mapInstance) {
      if (isOpen) {
        infoWindowInstanceRef.current.open(mapInstance);
      } else {
        infoWindowInstanceRef.current.close();
      }
    }
  }, [isOpen, mapInstance, infoWindowInstanceRef]);

  return {
    infoWindow: infoWindowInstanceRef.current,
  };
};

export default useInfoWindow;
