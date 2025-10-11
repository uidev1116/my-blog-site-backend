import qs from 'qs';
import loadGoogleMap from './loader';
import { isString, isStringArray } from '../../utils/typeGuard';

/**
 * マーカーのコレクション
 * マーカーのアイコン1つに対して、複数のマーカーを配置することができる
 */
interface MarkerCollection {
  icon: google.maps.MarkerOptions['icon'];
  options: google.maps.MarkerOptions[];
}

/**
 * Google Map
 * @param {HTMLImageElement} elment
 */
export default async (elment: HTMLImageElement) => {
  if (!elment.parentNode) {
    throw new Error('Google Map element must be a child of a parent element');
  }
  const { search } = new URL(elment.src);
  const query = qs.parse(search, { ignoreQueryPrefix: true });
  const msgs = elment.alt.split('[[:split:]]');
  if (!isString(query.key)) {
    // APIキーは文字列
    return;
  }

  if (query.key === '') {
    // APIキーは必須パラメータ
    return;
  }

  if (!isString(query.size)) {
    // サイズは必須パラメータ
    return;
  }

  if (query.size === '') {
    // サイズは必須パラメータ
    return;
  }

  if (!isString(query.center)) {
    // マーカーが存在しない場合、必須パラメータ
    return;
  }

  if (!isString(query.zoom)) {
    // ズームレベルは必須パラメータ
    return;
  }

  const google = await loadGoogleMap(query.key);
  const output = document.createElement('div');
  const size = query.size.split('x');
  const width = parseInt(size[0], 10) || null;
  const height = parseInt(size[1], 10) || null;
  const centerLatLng = query.center.split(',');
  const markerCollections: MarkerCollection[] = [];
  const googleInfoWindow = new google.maps.InfoWindow();
  const googleMap = new google.maps.Map(output, {
    // eslint-disable-line no-new
    center: { lat: parseFloat(centerLatLng[0]), lng: parseFloat(centerLatLng[1]) },
    zoom: parseInt(query.zoom, 10),
    mapTypeId: google.maps.MapTypeId.ROADMAP,
    scrollwheel: false,
    styles: ACMS.Config.s2dStyle,
  });

  output.className = elment.className;
  output.style.aspectRatio = `${width} / ${height}`;
  output.style.overflow = 'hidden';
  elment.parentNode.replaceChild(output, elment);

  // eslint-disable-next-line no-nested-ternary
  const markers = isString(query.markers) ? [query.markers] : isStringArray(query.markers) ? query.markers : [];

  markers.forEach((marker) => {
    const params = marker.split('|');
    const markerCollection: MarkerCollection = {
      icon: undefined,
      options: [],
    };
    if (!params.length) {
      return;
    }
    for (let i = 0; i < params.length; i += 1) {
      if (params[i].indexOf('icon:') === 0) {
        // icon
        markerCollection.icon = params[i].slice(5);
      } else {
        // markers's latlng
        const latLng = params[i].split(',');
        if (!latLng[0].length) {
          continue;
        } // latlngの不正なマーカー
        markerCollection.options.push({
          position: new google.maps.LatLng(parseFloat(latLng[0]), parseFloat(latLng[1])),
          map: googleMap,
        });
      }
    }
    markerCollections.push(markerCollection);
  });

  // marker collectionを展開してプロット
  let msgI = 0;
  markerCollections.forEach((markerCollection) => {
    for (let i = 0; i < markerCollection.options.length; i += 1) {
      const markerOption = markerCollection.options[i];
      const marker = new google.maps.Marker({ ...markerOption, icon: markerCollection.icon });

      // info windowのコンテンツがあれば表示（不正なマーカーがあった場合はズレる）
      if (msgs[msgI] !== undefined && width !== null && width >= 180) {
        ((gMarker, msg) => {
          if (msg) {
            google.maps.event.addListener(gMarker, 'click', () => {
              googleInfoWindow.setOptions({
                content: msg,
              });
              googleInfoWindow.open(googleMap, gMarker);
            });
          }
        })(
          marker,
          msgs[msgI]
            .replace(/\[\[:quot:\]\]/gim, '"')
            .replace(/\[\[:lt:\]\]/gim, '<')
            .replace(/\[\[:gt:\]\]/gim, '>')
            .replace(/\[\[:amp:\]\]/gim, '&')
        );
      }
      msgI += 1;
    }
  });
};
